// Import CSS for Vite. Path is relative to this JS file
import '../css/app.css'

//
import Alpine from 'alpinejs'
import focus from '@alpinejs/focus'
import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

gsap.registerPlugin(ScrollTrigger)
window.Alpine = Alpine

Alpine.plugin(focus)

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches

// Motion d'entrée + parallax — désactivé proprement en reduced motion via gsap.matchMedia()
const mm = gsap.matchMedia()
mm.add('(prefers-reduced-motion: no-preference)', () => {
	// Timeline d'entrée du hero (badge → titre → description → CTAs)
	const heroItems = gsap.utils.toArray('[data-hero-item]')
	if (heroItems.length) {
		gsap.from(heroItems, {
			opacity: 0,
			y: 32,
			duration: 0.8,
			ease: 'power3.out',
			stagger: 0.12,
			delay: 0.1,
		})
	}

	// Parallax léger sur les images de fond des heros
	gsap.utils.toArray('[data-hero-bg]').forEach((bg) => {
		gsap.set(bg, { scale: 1.12 })
		gsap.to(bg, {
			yPercent: 12,
			ease: 'none',
			scrollTrigger: {
				trigger: bg.parentElement,
				start: 'top top',
				end: 'bottom top',
				scrub: true,
			},
		})
	})
})

// Recalcule les positions une fois images/fonts chargées
window.addEventListener('load', () => ScrollTrigger.refresh())

// Compte à rebours réutilisable : x-data="countdown(timestampMs)"
Alpine.data('countdown', (target) => ({
	target,
	days: 0,
	hours: 0,
	minutes: 0,
	seconds: 0,
	isPast: false,
	init() {
		this.update()
		setInterval(() => this.update(), 1000)
	},
	update() {
		const diff = this.target - Date.now()
		if (diff <= 0) {
			this.isPast = true
			this.days = this.hours = this.minutes = this.seconds = 0
			return
		}
		this.days = Math.floor(diff / 86400000)
		this.hours = Math.floor((diff % 86400000) / 3600000)
		this.minutes = Math.floor((diff % 3600000) / 60000)
		this.seconds = Math.floor((diff % 60000) / 1000)
	},
	pad(n) {
		return String(n).padStart(2, '0')
	},
}))

// Calendrier mensuel : x-data="calendar(events)"
// events = [{ date: 'YYYY-MM-DD', time: 'HH:MM', title, type: 'stream'|'show', url }]
Alpine.data('calendar', (events) => ({
	events,
	month: 0,
	year: 0,
	init() {
		const today = new Date()
		this.month = today.getMonth()
		this.year = today.getFullYear()
	},
	get monthLabel() {
		return new Date(this.year, this.month, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
	},
	iso(day) {
		return `${this.year}-${String(this.month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
	},
	get cells() {
		const firstDay = new Date(this.year, this.month, 1)
		const startOffset = (firstDay.getDay() + 6) % 7 // semaine qui commence lundi
		const daysInMonth = new Date(this.year, this.month + 1, 0).getDate()
		const prevMonthDays = new Date(this.year, this.month, 0).getDate()
		const today = new Date()
		const todayIso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
		const cells = []
		for (let i = startOffset - 1; i >= 0; i--) {
			cells.push({ day: prevMonthDays - i, inMonth: false, isToday: false, events: [] })
		}
		for (let d = 1; d <= daysInMonth; d++) {
			const iso = this.iso(d)
			cells.push({
				day: d,
				inMonth: true,
				isToday: iso === todayIso,
				events: this.events.filter((e) => e.date === iso),
			})
		}
		let nextDay = 1
		while (cells.length % 7 !== 0) {
			cells.push({ day: nextDay++, inMonth: false, isToday: false, events: [] })
		}
		return cells
	},
	// Vue agenda (mobile) : événements du mois affiché, triés
	get agenda() {
		const prefix = `${this.year}-${String(this.month + 1).padStart(2, '0')}`
		return this.events
			.filter((e) => e.date.startsWith(prefix))
			.sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time))
	},
	agendaLabel(e) {
		return new Date(`${e.date}T12:00:00`).toLocaleDateString('fr-FR', {
			weekday: 'short',
			day: 'numeric',
			month: 'short',
		})
	},
	prev() {
		this.month--
		if (this.month < 0) {
			this.month = 11
			this.year--
		}
	},
	next() {
		this.month++
		if (this.month > 11) {
			this.month = 0
			this.year++
		}
	},
}))

// Scroll reveal directive: x-reveal on any element (GSAP + ScrollTrigger)
// Le modifier .delay + la variable CSS --i (index dans la boucle) créent le stagger
Alpine.directive('reveal', (el, { modifiers }) => {
	if (prefersReducedMotion()) return
	const index = parseFloat(getComputedStyle(el).getPropertyValue('--i')) || 0
	const delay = modifiers.includes('delay') ? Math.min(index * 0.08, 0.4) : 0
	gsap.from(el, {
		opacity: 0,
		y: 28,
		duration: 0.7,
		ease: 'power2.out',
		delay,
		scrollTrigger: {
			trigger: el,
			start: 'top 88%',
			once: true,
		},
	})
})

Alpine.start()
//

/**
 * Accept HMR as per: https://vitejs.dev/guide/api-hmr.html & https://nystudio107.com/docs/vite/
 */
if (import.meta.hot) {
	import.meta.hot.accept(() => {
		console.log('HMR')
	})
}
