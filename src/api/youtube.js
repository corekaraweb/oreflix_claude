// Requests go through our own server-side proxy (server/public/api/youtube.php)
// so the YouTube API key never reaches the browser. The proxy also only
// allows the playlist IDs the site admin has configured.
const PROXY_BASE = '/api/youtube.php'

export class YouTubeApiError extends Error {
  constructor(message, status) {
    super(message)
    this.name = 'YouTubeApiError'
    this.status = status
  }
}

async function apiGet(resource, params) {
  const url = new URL(PROXY_BASE, window.location.origin)
  url.searchParams.set('resource', resource)
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value)
    }
  })
  const res = await fetch(url.toString())
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    const message = data?.error?.message || `YouTube APIエラー (${res.status})`
    throw new YouTubeApiError(message, res.status)
  }
  return data
}

function pickThumbnail(thumbnails) {
  if (!thumbnails) return ''
  return (
    thumbnails.maxres?.url ||
    thumbnails.standard?.url ||
    thumbnails.high?.url ||
    thumbnails.medium?.url ||
    thumbnails.default?.url ||
    ''
  )
}

export async function fetchPlaylistMeta(playlistId) {
  const data = await apiGet('playlist', { id: playlistId })
  const item = data.items?.[0]
  if (!item) {
    throw new YouTubeApiError(
      '再生リストが見つかりませんでした。IDを確認してください。',
      404,
    )
  }
  return {
    id: playlistId,
    title: item.snippet.title,
    channelTitle: item.snippet.channelTitle,
    description: item.snippet.description,
    thumbnail: pickThumbnail(item.snippet.thumbnails),
    itemCount: item.contentDetails?.itemCount ?? 0,
  }
}

export async function fetchPlaylistItemsPage(playlistId, pageToken) {
  const data = await apiGet('playlistItems', { playlistId, pageToken })
  const items = (data.items || [])
    .filter((it) => {
      const title = it.snippet?.title
      return (
        it.snippet?.resourceId?.kind === 'youtube#video' &&
        title !== 'Private video' &&
        title !== 'Deleted video'
      )
    })
    .map((it) => ({
      id: it.contentDetails?.videoId || it.snippet.resourceId.videoId,
      title: it.snippet.title,
      description: it.snippet.description,
      thumbnail: pickThumbnail(it.snippet.thumbnails),
      channelTitle: it.snippet.videoOwnerChannelTitle || it.snippet.channelTitle,
      publishedAt: it.contentDetails?.videoPublishedAt || it.snippet.publishedAt,
      position: it.snippet.position,
    }))
  return { items, nextPageToken: data.nextPageToken }
}

export async function fetchVideosDetails(videoIds) {
  if (videoIds.length === 0) return {}
  const chunks = []
  for (let i = 0; i < videoIds.length; i += 50) {
    chunks.push(videoIds.slice(i, i + 50))
  }
  const result = {}
  for (const chunk of chunks) {
    const data = await apiGet('videos', { id: chunk.join(',') })
    for (const item of data.items || []) {
      result[item.id] = {
        duration: item.contentDetails?.duration,
        viewCount: item.statistics?.viewCount,
      }
    }
  }
  return result
}

export function parseIsoDuration(iso) {
  if (!iso) return ''
  const match = iso.match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/)
  if (!match) return ''
  const h = parseInt(match[1] || '0', 10)
  const m = parseInt(match[2] || '0', 10)
  const s = parseInt(match[3] || '0', 10)
  if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  return `${m}:${String(s).padStart(2, '0')}`
}

export function formatViewCount(count) {
  const n = Number(count)
  if (Number.isNaN(n)) return ''
  if (n >= 1e8) return `${(n / 1e8).toFixed(1)}億回視聴`
  if (n >= 1e4) return `${(n / 1e4).toFixed(1)}万回視聴`
  return `${n.toLocaleString('ja-JP')}回視聴`
}

export function formatPublishedDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('ja-JP', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  } catch {
    return ''
  }
}
