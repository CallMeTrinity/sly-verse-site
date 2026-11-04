// Générateur de show : interface CP (/admin/show-generator)
// Le moteur (génération + avertissements) est dans ./engine.js, sans dépendance au DOM.
import './show-generator.css'
import Alpine from 'alpinejs'
import {
	FORMATS,
	DEFAULT_STIPULATIONS,
	buildIndex,
	generateCard,
	validateCard,
	matchLabel,
	tokenName,
	tokenKey,
	wrestlerIdsOf,
	matchGender,
	newUid,
} from './engine.js'

const STORAGE_KEY = 'cwe-show-generator-params'

const PRESETS = {
	weeklyShow: { matchCount: 5, tagMatchCount: 1, womenMatchCount: 1, randomStipulations: false, allowMultiMan: false },
	premiumLiveEvent: {
		matchCount: 8,
		tagMatchCount: 1,
		womenMatchCount: 2,
		randomStipulations: true,
		allowMultiMan: true,
	},
	specialEvent: { matchCount: 6, tagMatchCount: 1, womenMatchCount: 1, randomStipulations: true, allowMultiMan: true },
}

const defaultParams = () => ({
	title: '',
	date: '',
	time: '20:00',
	showType: 'weeklyShow',
	rosters: [],
	includeWwe: true,
	includeInactive: false,
	titleIds: [],
	include: [],
	exclude: [],
	preferFaceHeel: true,
	preferSameRoster: true,
	genderMix: 'mixed',
	stipulationsText: DEFAULT_STIPULATIONS.join('\n'),
	...PRESETS.weeklyShow,
})

const loadParams = () => {
	try {
		const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null')
		return saved ? { ...defaultParams(), ...saved, title: '', date: '' } : defaultParams()
	} catch {
		return defaultParams()
	}
}

Alpine.data('showGenerator', () => ({
	data: { wrestlers: [], teams: [], titles: [], rosters: [], showTypes: [] },
	index: null,
	params: defaultParams(),
	matches: [],
	generationWarnings: [],
	generatedWith: null,
	picker: { include: '', exclude: '' },
	dragFrom: null,
	saving: false,
	created: null,
	formats: FORMATS,

	init() {
		this.data = JSON.parse(document.getElementById('show-generator-data').textContent)
		this.index = buildIndex(this.data)
		this.params = loadParams()
		// Les titres / superstars mémorisés peuvent ne plus exister
		this.params.titleIds = this.params.titleIds.filter((id) => this.index.titles.has(id))
		this.params.include = this.params.include.filter((k) => this.exists(k))
		this.params.exclude = this.params.exclude.filter((k) => this.exists(k))
		this.$watch('params', () => this.saveParams(), { deep: true })
	},

	saveParams() {
		try {
			const { title, date, ...rest } = this.params
			localStorage.setItem(STORAGE_KEY, JSON.stringify(rest))
		} catch {
			// stockage indisponible : simple confort, on ignore
		}
	},

	exists(key) {
		const id = Number(key.slice(1))
		return key[0] === 'w' ? this.index.wrestlers.has(id) : this.index.teams.has(id)
	},

	applyPreset() {
		Object.assign(this.params, PRESETS[this.params.showType] ?? {})
	},

	resetParams() {
		this.params = { ...defaultParams(), title: this.params.title, date: this.params.date }
	},

	/* ---------- Libellés ---------- */

	name(token) {
		return tokenName(token, this.index)
	},
	keyName(key) {
		return tokenName({ kind: key[0], id: Number(key.slice(1)) }, this.index)
	},
	label(match) {
		return matchLabel(match, this.index)
	},
	titleName(id) {
		return this.index.titles.get(id)?.name ?? '?'
	},
	championsLabel(title) {
		if (!title.champions.length) return 'Vacant'
		return title.champions.map((c) => tokenName(c, this.index)).join(' & ')
	},
	genderLabel(match) {
		const g = matchGender(match, this.index)
		return g === 'female' ? 'Féminin' : g === 'mixed' ? 'Mixte' : ''
	},
	tokenMeta(token) {
		if (token.kind === 't') return 'Tag team'
		const w = this.index.wrestlers.get(token.id)
		if (!w) return ''
		return [w.rating != null ? Math.round(w.rating) : null, w.alignment, w.origin === 'wwe' ? 'WWE' : null]
			.filter(Boolean)
			.join(' · ')
	},

	/* ---------- Sélecteurs imposer / exclure ---------- */

	candidates(list) {
		const q = this.picker[list].trim().toLowerCase()
		if (!q) return []
		const taken = new Set([...this.params.include, ...this.params.exclude])
		return [
			...this.data.wrestlers.map((w) => ({ key: `w${w.id}`, name: w.name, meta: w.roster ?? '' })),
			...this.data.teams.map((t) => ({ key: `t${t.id}`, name: t.name, meta: 'Tag team' })),
		]
			.filter((c) => !taken.has(c.key) && c.name.toLowerCase().includes(q))
			.slice(0, 8)
	},
	addTo(list, key) {
		if (!this.params[list].includes(key)) this.params[list].push(key)
		this.picker[list] = ''
	},
	removeFrom(list, key) {
		this.params[list] = this.params[list].filter((k) => k !== key)
	},
	toggle(list, value) {
		const arr = this.params[list]
		this.params[list] = arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value]
	},

	/* ---------- Génération ---------- */

	engineParams(overrides = {}) {
		return {
			...this.params,
			stipulations: this.params.stipulationsText
				.split('\n')
				.map((s) => s.trim())
				.filter(Boolean),
			...overrides,
		}
	},

	generate() {
		const locked = this.matches.map((m, position) => ({ ...m, position })).filter((m) => m.locked)
		const { matches, warnings } = generateCard(this.data, this.engineParams(), locked)
		this.matches = matches
		this.generationWarnings = warnings
		this.generatedWith = JSON.stringify(this.params)
		this.created = null
	},

	regenerateMatch(i) {
		const target = this.matches[i]
		const others = this.matches.map((m, position) => ({ ...m, position, locked: true })).filter((_, p) => p !== i)
		const women = this.matches.filter((m) => matchGender(m, this.index) === 'female').length
		const isWomen = matchGender(target, this.index) === 'female'
		const { matches, warnings } = generateCard(
			this.data,
			this.engineParams({
				matchCount: this.matches.length,
				tagMatchCount: this.matches.filter((m) => m.format === 'tag').length,
				womenMatchCount: isWomen ? women : 0,
				genderMix: isWomen ? 'female' : 'male',
				titleIds: target.titleIds ?? [],
				include: [],
			}),
			others,
		)
		// On ne récupère que le nouveau match, les autres gardent leur état de verrou
		const fresh = matches.find((m) => !others.some((o) => o.uid === m.uid))
		if (fresh) {
			fresh.stipulation = target.stipulation
			this.matches.splice(i, 1, fresh)
		} else {
			this.generationWarnings = [
				...warnings,
				`Impossible de régénérer le match n°${i + 1} : plus de participants libres.`,
			]
		}
	},

	addMatch() {
		this.matches.push({
			uid: newUid(),
			format: 'singles',
			sides: [[], []],
			titleIds: [],
			stake: '',
			stipulation: '',
			locked: false,
		})
	},
	removeMatch(i) {
		this.matches.splice(i, 1)
	},
	clearCard() {
		this.matches = []
		this.generationWarnings = []
		this.generatedWith = null
		this.created = null
	},

	/* ---------- Édition d'un match ---------- */

	setFormat(match, format) {
		match.format = format
		const sides = FORMATS[format].sides
		while (match.sides.length < sides) match.sides.push([])
		while (match.sides.length > sides && !match.sides[match.sides.length - 1].length) match.sides.pop()
	},
	setTitle(match, value) {
		const id = Number(value)
		match.titleIds = id ? [id] : []
		match.stake = id ? this.titleName(id) : ''
	},
	removeToken(match, s, t) {
		match.sides[s].splice(t, 1)
	},
	addToken(match, s, key) {
		if (!key) return
		match.sides[s].push({ kind: key[0], id: Number(key.slice(1)) })
	},
	usedIn(key) {
		const token = { kind: key[0], id: Number(key.slice(1)) }
		const ids = wrestlerIdsOf(token, this.index)
		const n = this.matches.findIndex((m) =>
			m.sides.flat().some((t) => wrestlerIdsOf(t, this.index).some((id) => ids.includes(id))),
		)
		return n === -1 ? '' : ` (match n°${n + 1})`
	},

	/* ---------- Ordre ---------- */

	move(from, to) {
		if (to < 0 || to >= this.matches.length || from === to) return
		const [m] = this.matches.splice(from, 1)
		this.matches.splice(to, 0, m)
	},
	onDragStart(i, event) {
		this.dragFrom = i
		event.dataTransfer.effectAllowed = 'move'
		event.dataTransfer.setData('text/plain', String(i))
	},
	onDrop(i) {
		if (this.dragFrom !== null) this.move(this.dragFrom, i)
		this.dragFrom = null
	},

	/* ---------- Avertissements ---------- */

	get liveWarnings() {
		if (!this.matches.length) return []
		return validateCard(this.matches, this.data, this.params)
	},
	get paramsChanged() {
		return this.generatedWith !== null && this.generatedWith !== JSON.stringify(this.params)
	},
	get paramWarnings() {
		// Contradictions visibles avant de générer ; après génération, le moteur les reformule
		if (this.generatedWith !== null && !this.paramsChanged) return []
		const w = []
		const p = this.params
		const n = Number(p.matchCount) || 0
		if (Number(p.tagMatchCount) > n) w.push(`Plus de matchs par équipe (${p.tagMatchCount}) que de matchs (${n}).`)
		if (Number(p.womenMatchCount) > n) w.push(`Plus de matchs féminins (${p.womenMatchCount}) que de matchs (${n}).`)
		if (p.titleIds.length > n) w.push(`Plus de titres défendus (${p.titleIds.length}) que de matchs (${n}).`)
		if (p.genderMix === 'male' && Number(p.womenMatchCount) > 0)
			w.push(
				'Carte « masculine uniquement » alors que des matchs féminins sont demandés : les matchs féminins sont prioritaires.',
			)
		const both = p.include.filter((k) => p.exclude.includes(k))
		both.forEach((k) => w.push(`${this.keyName(k)} est à la fois imposé et exclu.`))
		for (const id of p.titleIds) {
			const title = this.index.titles.get(id)
			for (const c of title?.champions ?? []) {
				if (p.exclude.includes(tokenKey(c)))
					w.push(`${tokenName(c, this.index)} est exclu mais défend le ${title.name}.`)
			}
		}
		return w
	},

	/* ---------- Création ---------- */

	async createShow() {
		if (!this.params.title.trim()) {
			Craft.cp.displayError('Donne un nom au show avant de le créer.')
			this.$refs.title.focus()
			return
		}
		if (!this.matches.length) {
			Craft.cp.displayError('La carte est vide.')
			return
		}
		this.saving = true
		try {
			const payload = {
				title: this.params.title,
				date: this.params.date,
				time: this.params.time,
				showType: this.params.showType,
				matches: this.matches.map((m) => ({
					label: this.label(m),
					fighterIds: m.sides.flat().map((t) => t.id),
					format: m.format,
					titleIds: m.titleIds,
					stipulation: m.stipulation,
				})),
			}
			const { data } = await Craft.sendActionRequest('POST', 'my-module/show-generator/create', { data: payload })
			this.created = data
			Craft.cp.displaySuccess(data.message)
		} catch (e) {
			Craft.cp.displayError(e?.response?.data?.message ?? 'Impossible de créer le show.')
		} finally {
			this.saving = false
		}
	},
}))

window.Alpine = window.Alpine ?? Alpine
Alpine.start()
