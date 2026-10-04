import { useEffect, useRef, useState } from 'react'
import {
  fetchPlaylistItemsPage,
  fetchPlaylistMeta,
  fetchVideosDetails,
  parseIsoDuration,
} from '../api/youtube'

const MAX_PAGES = 4 // caps each playlist at ~200 videos to keep quota usage sane

async function loadPlaylist(playlistId, { signal, onUpdate }) {
  onUpdate({ status: 'loading', error: null })
  try {
    const meta = await fetchPlaylistMeta(playlistId)
    if (signal.aborted) return
    onUpdate({ status: 'loading', meta })

    let videos = []
    let pageToken
    let pageCount = 0

    do {
      const { items, nextPageToken } = await fetchPlaylistItemsPage(playlistId, pageToken)
      if (signal.aborted) return

      const details = await fetchVideosDetails(items.map((item) => item.id))
      if (signal.aborted) return

      const enriched = items.map((item) => ({
        ...item,
        duration: parseIsoDuration(details[item.id]?.duration),
        viewCount: details[item.id]?.viewCount,
      }))

      videos = videos.concat(enriched)
      pageToken = nextPageToken
      pageCount += 1

      const hasMore = Boolean(pageToken) && pageCount < MAX_PAGES
      onUpdate({
        status: hasMore ? 'loading-more' : 'ready',
        meta,
        videos: [...videos],
      })
    } while (pageToken && pageCount < MAX_PAGES)
  } catch (err) {
    if (signal.aborted) return
    onUpdate({ status: 'error', error: err.message || String(err) })
  }
}

export function usePlaylists(playlistIds) {
  const [state, setState] = useState({})
  const controllersRef = useRef({})

  const startLoad = (id) => {
    controllersRef.current[id]?.abort()
    const controller = new AbortController()
    controllersRef.current[id] = controller
    loadPlaylist(id, {
      signal: controller.signal,
      onUpdate: (patch) =>
        setState((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } })),
    })
  }

  // Load newly-added playlists and drop removed ones, without refetching
  // playlists that are already loaded or loading.
  useEffect(() => {
    playlistIds.forEach((id) => {
      if (!controllersRef.current[id]) startLoad(id)
    })
    Object.keys(controllersRef.current).forEach((id) => {
      if (!playlistIds.includes(id)) {
        controllersRef.current[id].abort()
        delete controllersRef.current[id]
      }
    })
    setState((prev) => {
      const next = {}
      playlistIds.forEach((id) => {
        if (prev[id]) next[id] = prev[id]
      })
      return next
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(playlistIds)])

  useEffect(
    () => () => {
      Object.values(controllersRef.current).forEach((c) => c.abort())
    },
    [],
  )

  const retry = (id) => startLoad(id)

  return { playlistsState: state, retry }
}
