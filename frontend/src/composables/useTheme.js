import { ref, computed, watch } from 'vue'

const mode = ref('auto') // 'auto' | 'light' | 'dark'
const mq = typeof window !== 'undefined' && window.matchMedia
	? window.matchMedia('(prefers-color-scheme: dark)')
	: null
const osPrefersDark = ref(mq ? mq.matches : false)

function handleMq(e) {
	osPrefersDark.value = e.matches
	if (mode.value === 'auto') applyTheme()
}

export function applyTheme() {
	const dark = mode.value === 'dark' || (mode.value === 'auto' && osPrefersDark.value)
	document.documentElement.classList.toggle('dark', !!dark)
}

export function initTheme(initial) {
	try {
		const saved = localStorage.getItem('theme.mode')
		if (initial) mode.value = initial
		else if (saved === 'auto' || saved === 'light' || saved === 'dark') mode.value = saved
	} catch { }

	if (mq) osPrefersDark.value = mq.matches
	if (mq && mode.value === 'auto') mq.addEventListener('change', handleMq)

	applyTheme()

	watch(mode, (m, prev) => {
		try { localStorage.setItem('theme.mode', m) } catch { }
		if (mq) {
			if (m === 'auto' && prev !== 'auto') mq.addEventListener('change', handleMq)
			if (prev === 'auto' && m !== 'auto') mq.removeEventListener('change', handleMq)
		}
		applyTheme()
	})
}

export function useTheme() {
	const isDark = computed(() => mode.value === 'dark' || (mode.value === 'auto' && osPrefersDark.value))
	const setMode = (m) => { mode.value = m }
	return { mode, isDark, setMode, applyTheme }
}
