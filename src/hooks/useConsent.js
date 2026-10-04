import { useState } from 'react'

const STORAGE_KEY = 'oreflix:consent'

function hasConsented() {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

/**
 * Whether the visitor has acknowledged that this site uses YouTube API
 * Services. Until they have, no YouTube player (hero, video cards, modal)
 * may be loaded — only static YouTube-provided thumbnails.
 */
export function useConsent() {
  const [consented, setConsented] = useState(hasConsented)

  const accept = () => {
    try {
      window.localStorage.setItem(STORAGE_KEY, '1')
    } catch {
      // ignore storage errors; the banner will just reappear next visit
    }
    setConsented(true)
  }

  return [consented, accept]
}
