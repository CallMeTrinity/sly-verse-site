// Import CSS for Vite. Path is relative to this JS file
import '../css/app.css'

//
import Alpine from 'alpinejs'
import focus from '@alpinejs/focus'
window.Alpine = Alpine

Alpine.plugin(focus)

// Scroll reveal directive: x-reveal on any element
// Adds 'revealed' class when element enters viewport
Alpine.directive('reveal', (el, { modifiers }) => {
	const delay = modifiers.includes('delay') ? 200 : 0
	el.classList.add('reveal')
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					setTimeout(() => el.classList.add('revealed'), delay)
					observer.unobserve(el)
				}
			})
		},
		{ threshold: 0 },
	)
	observer.observe(el)
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
