/**
 * Moteur du générateur de show (pur, sans DOM) : génération de carte et avertissements.
 *
 * Vocabulaire
 * - wrestler : { id, name, gender, roster, origin, alignment, rating, active }
 * - team     : { id, name, memberIds, roster, origin, alignment, active }
 * - title    : { id, name, champions: [{ kind, id }], isTag, gender }
 * - token    : { kind: 'w' | 't', id }  (participant : superstar ou tag team)
 * - match    : { uid, format, sides: token[][], titleIds, stake, stipulation, locked }
 *
 * Règle d'or : un paramètre contradictoire ne bloque jamais la génération.
 * Le moteur fait au mieux et renvoie un avertissement lisible.
 */

export const FORMATS = {
	singles: { label: 'Simple', sides: 2, perSide: 1 },
	triple: { label: 'Triple Threat', sides: 3, perSide: 1 },
	fatal4: { label: 'Fatal 4-Way', sides: 4, perSide: 1 },
	tag: { label: 'Tag Team', sides: 2, perSide: 2 },
}

export const DEFAULT_STIPULATIONS = [
	'Extreme Rules Match',
	'No Holds Barred Match',
	'Last Man Standing Match',
	'Falls Count Anywhere Match',
	'Street Fight',
	'Steel Cage Match',
	'Ladder Match',
	'TLC Match',
	'Hell in a Cell Match',
	'I Quit Match',
]

let uidCounter = 0
export const newUid = () => `m${Date.now().toString(36)}${(uidCounter++).toString(36)}`

/* ------------------------------------------------------------------ */
/* Aléatoire                                                           */
/* ------------------------------------------------------------------ */

export function makeRng(seed = Date.now()) {
	// mulberry32 : reproductible si on fournit une graine (tests)
	let a = seed >>> 0
	return () => {
		a = (a + 0x6d2b79f5) >>> 0
		let t = a
		t = Math.imul(t ^ (t >>> 15), t | 1)
		t ^= t + Math.imul(t ^ (t >>> 7), t | 61)
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296
	}
}

const shuffle = (list, rng) => {
	const a = list.slice()
	for (let i = a.length - 1; i > 0; i--) {
		const j = Math.floor(rng() * (i + 1))
		;[a[i], a[j]] = [a[j], a[i]]
	}
	return a
}

// Tirage pondéré : score plus élevé = plus de chances, sans être déterministe
const weightedPick = (list, score, rng) => {
	if (!list.length) return null
	const weights = list.map((x) => Math.max(0.1, score(x)))
	const total = weights.reduce((s, w) => s + w, 0)
	let r = rng() * total
	for (let i = 0; i < list.length; i++) {
		r -= weights[i]
		if (r <= 0) return list[i]
	}
	return list[list.length - 1]
}

/* ------------------------------------------------------------------ */
/* Index / helpers                                                     */
/* ------------------------------------------------------------------ */

export function buildIndex(data) {
	const wrestlers = new Map(data.wrestlers.map((w) => [w.id, w]))
	const teams = new Map(data.teams.map((t) => [t.id, t]))
	const titles = new Map(data.titles.map((t) => [t.id, t]))
	return { wrestlers, teams, titles }
}

export const tokenKey = (t) => `${t.kind}${t.id}`

// Superstars réellement présentes derrière un participant (une team = ses membres)
export function wrestlerIdsOf(token, index) {
	if (token.kind === 'w') return [token.id]
	return index.teams.get(token.id)?.memberIds ?? []
}

export function tokenName(token, index) {
	const item = token.kind === 'w' ? index.wrestlers.get(token.id) : index.teams.get(token.id)
	return item ? item.name : '?'
}

export function sideName(side, index) {
	return side.map((t) => tokenName(t, index)).join(' & ')
}

export function matchLabel(match, index) {
	return match.sides.map((s) => sideName(s, index)).join(' vs ')
}

const avg = (list) => (list.length ? list.reduce((s, x) => s + x, 0) / list.length : 0)

function tokenRating(token, index) {
	return avg(wrestlerIdsOf(token, index).map((id) => index.wrestlers.get(id)?.rating ?? 70))
}

function tokenGender(token, index) {
	const genders = new Set(wrestlerIdsOf(token, index).map((id) => index.wrestlers.get(id)?.gender ?? null))
	if (genders.size === 1) return [...genders][0]
	return genders.size ? 'mixed' : null
}

export function matchGender(match, index) {
	const genders = new Set(match.sides.flat().map((t) => tokenGender(t, index)))
	genders.delete(null)
	if (genders.size === 1) return [...genders][0]
	return genders.size ? 'mixed' : null
}

/* ------------------------------------------------------------------ */
/* Génération                                                          */
/* ------------------------------------------------------------------ */

/**
 * @param data     { wrestlers, teams, titles }
 * @param params   paramètres du formulaire (cf. defaultParams)
 * @param locked   matchs verrouillés à conserver tels quels
 * @param rng      fonction aléatoire [0, 1)
 * @returns { matches, warnings }
 */
export function generateCard(data, params, locked = [], rng = Math.random) {
	const index = buildIndex(data)
	const warnings = []
	const warn = (text) => warnings.push(text)

	const include = new Set(params.include ?? [])
	const exclude = new Set(params.exclude ?? [])
	const rosters = new Set(params.rosters ?? [])

	// --- Contradictions de paramètres -----------------------------------
	for (const key of include) {
		if (exclude.has(key)) {
			warn(`${tokenName(parseKey(key), index)} est à la fois imposé et exclu : imposé l'emporte.`)
			exclude.delete(key)
		}
	}

	// --- Pool disponible -------------------------------------------------
	const inRosters = (item) => !rosters.size || rosters.has(item.roster ?? '')
	const eligibleWrestler = (w) =>
		(params.includeInactive || w.active) &&
		(params.includeWwe || w.origin !== 'wwe') &&
		inRosters(w) &&
		!exclude.has(`w${w.id}`)
	const eligibleTeam = (t) =>
		(params.includeInactive || t.active) &&
		(params.includeWwe || t.origin !== 'wwe') &&
		inRosters(t) &&
		!exclude.has(`t${t.id}`) &&
		t.memberIds.every((id) => !exclude.has(`w${id}`))

	for (const key of include) {
		const token = parseKey(key)
		const item = token.kind === 'w' ? index.wrestlers.get(token.id) : index.teams.get(token.id)
		if (!item) continue
		if (!params.includeWwe && item.origin === 'wwe')
			warn(`${item.name} est une superstar WWE alors que les superstars WWE sont décochées : incluse quand même.`)
		if (!params.includeInactive && !item.active)
			warn(`${item.name} est inactif(ve) mais imposé(e) : inclus(e) quand même.`)
		if (!inRosters(item)) warn(`${item.name} n'appartient à aucun roster sélectionné : inclus(e) quand même.`)
	}

	const used = new Set() // IDs de superstars déjà placées
	const usedTeams = new Set()
	const markUsed = (token) => {
		wrestlerIdsOf(token, index).forEach((id) => used.add(id))
		if (token.kind === 't') usedTeams.add(token.id)
	}
	const isFree = (token) => wrestlerIdsOf(token, index).every((id) => !used.has(id))

	const freeWrestlers = (filter = () => true) =>
		data.wrestlers.filter((w) => !used.has(w.id) && (eligibleWrestler(w) || include.has(`w${w.id}`)) && filter(w))
	const freeTeams = (filter = () => true) =>
		data.teams.filter(
			(t) =>
				!usedTeams.has(t.id) &&
				isFree({ kind: 't', id: t.id }) &&
				(eligibleTeam(t) || include.has(`t${t.id}`)) &&
				filter(t),
		)

	// Choix d'un adversaire : préfère l'alignement opposé, le même roster et un niveau proche
	const opponentScore = (reference) => (candidate) => {
		let score = 1
		const refAlign = reference?.alignment
		if (params.preferFaceHeel && refAlign && candidate.alignment) {
			if (
				(refAlign === 'face' && candidate.alignment === 'heel') ||
				(refAlign === 'heel' && candidate.alignment === 'face')
			)
				score += 3
			else if (refAlign === candidate.alignment) score -= 0.5
		}
		if (params.preferSameRoster && reference?.roster && candidate.roster === reference.roster) score += 2
		if (reference?.rating != null && candidate.rating != null) {
			score += Math.max(0, 2 - Math.abs(reference.rating - candidate.rating) / 10)
		}
		if (include.has(`w${candidate.id}`) || include.has(`t${candidate.id}`)) score += 6
		return score
	}

	// Équipe improvisée : deux superstars libres de même genre
	const makeshiftTeam = (gender, reference) => {
		const pool = freeWrestlers((w) => !gender || gender === 'mixed' || w.gender === gender)
		if (pool.length < 2) return null
		const first = weightedPick(pool, opponentScore(reference), rng)
		const second = weightedPick(
			pool.filter((w) => w.id !== first.id),
			(w) => opponentScore(reference)(w) + (w.alignment === first.alignment ? 2 : 0),
			rng,
		)
		return [
			{ kind: 'w', id: first.id },
			{ kind: 'w', id: second.id },
		]
	}

	const matches = []
	const push = (match) => {
		match.sides.flat().forEach(markUsed)
		matches.push(match)
	}

	// Les matchs verrouillés occupent leurs participants
	for (const match of locked) {
		match.sides.flat().forEach(markUsed)
	}

	// --- Nombre de matchs ----------------------------------------------
	const lockedCount = locked.length
	const titleIds = (params.titleIds ?? []).filter((id) => index.titles.has(id))
	let target = Math.max(1, Number(params.matchCount) || 1)
	const tagTarget = Math.max(0, Number(params.tagMatchCount) || 0)
	const womenTarget = Math.max(0, Number(params.womenMatchCount) || 0)

	if (tagTarget > target)
		warn(
			`${tagTarget} matchs par équipe demandés pour ${target} matchs au total : la carte passe à ${tagTarget} matchs.`,
		)
	if (titleIds.length > target)
		warn(
			`${titleIds.length} titres défendus pour ${target} matchs au total : la carte passe à ${titleIds.length} matchs.`,
		)
	if (womenTarget > target) warn(`${womenTarget} matchs féminins demandés pour ${target} matchs au total.`)
	target = Math.max(target, tagTarget, titleIds.length)

	// --- 1. Titres défendus --------------------------------------------
	const titleMatchByChampion = new Map()
	for (const titleId of titleIds) {
		if (locked.some((m) => (m.titleIds ?? []).includes(titleId))) continue
		const title = index.titles.get(titleId)
		const champions = (title.champions ?? []).filter((c) =>
			c.kind === 'w' ? index.wrestlers.has(c.id) : index.teams.has(c.id),
		)

		// Champion déjà dans un autre match de titre : on fusionne les enjeux
		const championKey = champions.map(tokenKey).sort().join('|')
		if (championKey && titleMatchByChampion.has(championKey)) {
			const existing = titleMatchByChampion.get(championKey)
			existing.titleIds.push(titleId)
			existing.stake = existing.titleIds.map((id) => index.titles.get(id).name).join(' + ')
			warn(
				`${sideName(champions, index)} détient plusieurs titres défendus : ils sont mis en jeu dans le même match.`,
			)
			continue
		}

		const championBusy = champions.some((c) => !isFree(c))
		if (championBusy) {
			warn(
				`Le champion ${title.name} (${sideName(champions, index)}) est déjà pris dans un match verrouillé ou imposé : titre non défendu.`,
			)
			continue
		}

		for (const c of champions) {
			const key = tokenKey(c)
			const item = c.kind === 'w' ? index.wrestlers.get(c.id) : index.teams.get(c.id)
			if (exclude.has(key)) warn(`${item.name} est exclu(e) mais défend le ${title.name} : inclus(e) quand même.`)
			else if (!params.includeInactive && !item.active)
				warn(`${item.name} est inactif(ve) mais défend le ${title.name}.`)
			else if (!inRosters(item))
				warn(`${item.name} (${title.name}) n'est dans aucun roster sélectionné : inclus(e) quand même.`)
		}

		const isTag = title.isTag
		const gender = title.gender ?? (champions[0] ? tokenGender(champions[0], index) : null)
		const reference = champions[0]
			? champions[0].kind === 'w'
				? index.wrestlers.get(champions[0].id)
				: index.teams.get(champions[0].id)
			: null

		let championSide = champions.length ? champions.slice() : null
		if (isTag && championSide && championSide.length === 1 && championSide[0].kind === 'w') {
			// Titre par équipe détenu par une seule superstar renseignée
			const partner = makeshiftTeam(gender, reference)?.find((t) => t.id !== championSide[0].id)
			if (partner) {
				championSide.push(partner)
				warn(
					`${title.name} : un seul champion renseigné (${tokenName(championSide[0], index)}), partenaire tiré au sort.`,
				)
			}
		}

		if (!championSide) {
			// Titre vacant : deux prétendants
			warn(`${title.name} est vacant : match pour le titre vacant entre deux prétendants.`)
		} else {
			championSide.forEach(markUsed)
		}

		const challengerSide = () => {
			if (isTag) {
				const teams = freeTeams(
					(t) => !gender || gender === 'mixed' || tokenGender({ kind: 't', id: t.id }, index) === gender,
				)
				const team = weightedPick(teams, opponentScore(reference), rng)
				if (team) return [{ kind: 't', id: team.id }]
				return makeshiftTeam(gender, reference)
			}
			const pool = freeWrestlers((w) => !gender || gender === 'mixed' || w.gender === gender)
			// Aspirant : niveau élevé privilégié
			const pick = weightedPick(pool, (w) => opponentScore(reference)(w) + (w.rating ?? 70) / 25, rng)
			return pick ? [{ kind: 'w', id: pick.id }] : null
		}

		const sides = []
		if (championSide) sides.push(championSide)
		while (sides.length < 2) {
			const side = challengerSide()
			if (!side) break
			side.forEach(markUsed)
			sides.push(side)
		}
		if (sides.length < 2) {
			warn(
				`${title.name} : aucun aspirant disponible${gender ? ' du même genre' : ''}, match incomplet à compléter à la main.`,
			)
		}

		const match = {
			uid: newUid(),
			format: isTag ? 'tag' : 'singles',
			sides,
			titleIds: [titleId],
			stake: title.name,
			stipulation: '',
			locked: false,
		}
		sides.flat().forEach(markUsed)
		matches.push(match)
		if (championKey) titleMatchByChampion.set(championKey, match)
	}

	// --- 2. Participants imposés ----------------------------------------
	for (const key of include) {
		const token = parseKey(key)
		if (!isFree(token) || (token.kind === 't' && usedTeams.has(token.id))) continue
		if (lockedCount + matches.length >= target) {
			const name = tokenName(token, index)
			warn(`Plus de place sur la carte pour ${name} : un match supplémentaire est ajouté.`)
			target++
		}
		if (token.kind === 't') {
			const team = index.teams.get(token.id)
			const gender = tokenGender(token, index)
			markUsed(token)
			const other = weightedPick(
				freeTeams((t) => tokenGender({ kind: 't', id: t.id }, index) === gender),
				opponentScore(team),
				rng,
			)
			const otherSide = other ? [{ kind: 't', id: other.id }] : makeshiftTeam(gender, team)
			if (!otherSide) warn(`Aucune équipe adverse disponible pour ${team.name}.`)
			push({
				uid: newUid(),
				format: 'tag',
				sides: otherSide ? [[token], otherSide] : [[token]],
				titleIds: [],
				stake: '',
				stipulation: '',
				locked: false,
			})
		} else {
			const wrestler = index.wrestlers.get(token.id)
			markUsed(token)
			const opponent = weightedPick(
				freeWrestlers((w) => w.gender === wrestler.gender),
				opponentScore(wrestler),
				rng,
			)
			if (!opponent) warn(`Aucun adversaire disponible pour ${wrestler.name}.`)
			push({
				uid: newUid(),
				format: 'singles',
				sides: opponent ? [[token], [{ kind: 'w', id: opponent.id }]] : [[token]],
				titleIds: [],
				stake: '',
				stipulation: '',
				locked: false,
			})
		}
	}

	const count = (pred) => [...locked, ...matches].filter(pred).length
	const remaining = () => target - lockedCount - matches.length

	// --- 3. Matchs par équipe -------------------------------------------
	const genderForNext = () => {
		const women = count((m) => matchGender(m, index) === 'female')
		if (women < womenTarget) return 'female'
		if (params.genderMix === 'male') return 'male'
		if (params.genderMix === 'female') return 'female'
		return rng() < 0.3 ? 'female' : 'male'
	}

	let improvisedWarned = false
	while (count((m) => m.format === 'tag') < tagTarget && remaining() > 0) {
		const gender = genderForNext()
		let sides = []
		for (const g of [gender, gender === 'female' ? 'male' : 'female']) {
			const teams = shuffle(
				freeTeams((t) => tokenGender({ kind: 't', id: t.id }, index) === g),
				rng,
			)
			sides = teams.slice(0, 2).map((t) => [{ kind: 't', id: t.id }])
			sides.forEach((s) => s.forEach(markUsed))
			while (sides.length < 2) {
				const pair = makeshiftTeam(g, null)
				if (!pair) break
				if (!improvisedWarned) {
					warn('Pas assez de tag teams disponibles : des équipes improvisées sont formées.')
					improvisedWarned = true
				}
				pair.forEach(markUsed)
				sides.push(pair)
			}
			if (sides.length === 2) break
			sides.flat().forEach((t) => wrestlerIdsOf(t, index).forEach((id) => used.delete(id)))
			sides.forEach((s) => s.forEach((t) => t.kind === 't' && usedTeams.delete(t.id)))
			sides = []
		}
		if (sides.length < 2) {
			warn(`Pas assez de participants pour ${tagTarget} matchs par équipe.`)
			break
		}
		push({ uid: newUid(), format: 'tag', sides, titleIds: [], stake: '', stipulation: '', locked: false })
	}

	// --- 4. Matchs restants ----------------------------------------------
	let shortageWarned = false
	while (remaining() > 0) {
		let gender = genderForNext()
		let pool = freeWrestlers((w) => w.gender === gender)
		if (pool.length < 2) {
			gender = gender === 'female' ? 'male' : 'female'
			pool = freeWrestlers((w) => w.gender === gender)
		}
		if (pool.length < 2) {
			if (!shortageWarned) {
				warn(
					`Pas assez de superstars disponibles : ${lockedCount + matches.length} match(s) générés sur ${target} demandés. Élargis les rosters ou réduis le nombre de matchs.`,
				)
				shortageWarned = true
			}
			break
		}

		let format = 'singles'
		if (params.allowMultiMan && pool.length >= 3 && rng() < 0.2)
			format = pool.length >= 4 && rng() < 0.4 ? 'fatal4' : 'triple'
		const size = FORMATS[format].sides
		const first = weightedPick(pool, (w) => 1 + (include.has(`w${w.id}`) ? 6 : 0), rng)
		const sides = [[{ kind: 'w', id: first.id }]]
		markUsed(sides[0][0])
		while (sides.length < size) {
			const next = weightedPick(
				freeWrestlers((w) => w.gender === gender),
				opponentScore(first),
				rng,
			)
			if (!next) break
			const token = { kind: 'w', id: next.id }
			markUsed(token)
			sides.push([token])
		}
		push({ uid: newUid(), format, sides, titleIds: [], stake: '', stipulation: '', locked: false })
	}

	const womenCount = count((m) => matchGender(m, index) === 'female')
	if (womenCount < womenTarget)
		warn(
			`Seulement ${womenCount} match(s) féminin(s) sur ${womenTarget} demandés : pas assez de superstars féminines disponibles.`,
		)

	// --- 5. Stipulations --------------------------------------------------
	const stipulations = (params.stipulations ?? []).filter(Boolean)
	if (params.randomStipulations && stipulations.length) {
		for (const match of matches) {
			if (rng() < (params.showType === 'premiumLiveEvent' ? 0.45 : 0.15)) {
				match.stipulation = stipulations[Math.floor(rng() * stipulations.length)]
			}
		}
	} else if (params.randomStipulations && !stipulations.length) {
		warn('Stipulations aléatoires activées mais la liste des stipulations est vide.')
	}

	// --- 6. Ordre : matchs verrouillés à leur place, le reste trié --------
	const ordered = orderMatches(matches, index)
	const result = []
	const lockedByPos = new Map(locked.map((m) => [m.position, m]))
	let cursor = 0
	const total = locked.length + ordered.length
	for (let pos = 0; pos < total; pos++) {
		if (lockedByPos.has(pos)) result.push(lockedByPos.get(pos))
		else if (cursor < ordered.length) result.push(ordered[cursor++])
	}
	// Positions verrouillées au-delà de la nouvelle longueur
	for (const m of locked) if (!result.includes(m)) result.push(m)

	return { matches: result.map(({ position, ...m }) => m), warnings }
}

// Main event en dernier, deuxième plus gros match en ouverture
export function matchScore(match, index) {
	const ratings = match.sides.flat().map((t) => tokenRating(t, index))
	let score = avg(ratings)
	if (match.titleIds?.length) score += 30 + 10 * (match.titleIds.length - 1)
	if (
		match.titleIds?.some((id) =>
			/world|universal|undisputed|heavyweight|monde/i.test(index.titles.get(id)?.name ?? ''),
		)
	)
		score += 15
	if (match.stipulation) score += 5
	return score
}

export function orderMatches(matches, index) {
	if (matches.length < 3) return matches.slice().sort((a, b) => matchScore(a, index) - matchScore(b, index))
	const sorted = matches.slice().sort((a, b) => matchScore(a, index) - matchScore(b, index))
	const secondBest = sorted.splice(sorted.length - 2, 1)[0]
	return [secondBest, ...sorted]
}

export function parseKey(key) {
	return { kind: key[0], id: Number(key.slice(1)) }
}

/* ------------------------------------------------------------------ */
/* Vérification d'une carte (après édition manuelle)                   */
/* ------------------------------------------------------------------ */

export function validateCard(matches, data, params = {}) {
	const index = buildIndex(data)
	const warnings = []
	const seen = new Map()

	matches.forEach((match, i) => {
		const n = i + 1
		const format = FORMATS[match.format] ?? FORMATS.singles
		const filled = match.sides.filter((s) => s.length)
		if (filled.length < 2) warnings.push(`Match n°${n} : il faut au moins deux camps.`)
		if (match.format === 'tag' && match.sides.some((s) => s.flatMap((t) => wrestlerIdsOf(t, index)).length < 2))
			warnings.push(`Match n°${n} : match par équipe avec un camp incomplet.`)
		if (match.format !== 'tag' && filled.length && filled.length !== format.sides)
			warnings.push(
				`Match n°${n} : ${format.label} prévu pour ${format.sides} participants, ${filled.length} présents.`,
			)

		for (const token of match.sides.flat()) {
			for (const id of wrestlerIdsOf(token, index)) {
				if (seen.has(id) && seen.get(id) !== n) {
					warnings.push(
						`${index.wrestlers.get(id)?.name ?? '?'} apparaît dans les matchs n°${seen.get(id)} et n°${n}.`,
					)
				}
				seen.set(id, n)
			}
		}

		const gender = matchGender(match, index)
		for (const titleId of match.titleIds ?? []) {
			const title = index.titles.get(titleId)
			if (!title) continue
			if (title.gender && gender && gender !== 'mixed' && gender !== title.gender)
				warnings.push(
					`Match n°${n} : le ${title.name} est mis en jeu dans un match ${gender === 'female' ? 'féminin' : 'masculin'}.`,
				)
			const championKeys = (title.champions ?? []).map(tokenKey)
			const present = new Set(match.sides.flat().map(tokenKey))
			if (championKeys.length && !championKeys.some((k) => present.has(k)))
				warnings.push(`Match n°${n} : le champion du ${title.name} ne fait pas partie du match.`)
		}
	})

	const titleCount = new Map()
	matches.forEach((m) => (m.titleIds ?? []).forEach((id) => titleCount.set(id, (titleCount.get(id) ?? 0) + 1)))
	for (const [id, c] of titleCount) {
		if (c > 1) warnings.push(`Le ${index.titles.get(id)?.name ?? 'titre'} est mis en jeu dans ${c} matchs.`)
	}

	if (params.matchCount && matches.length !== Number(params.matchCount))
		warnings.push(`La carte compte ${matches.length} matchs (${params.matchCount} demandés).`)

	return [...new Set(warnings)]
}
