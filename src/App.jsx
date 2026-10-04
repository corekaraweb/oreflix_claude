import { useEffect, useMemo, useState } from 'react'
import './App.css'
import Navbar from './components/Navbar'
import Hero from './components/Hero'
import Row from './components/Row'
import VideoCard from './components/VideoCard'
import VideoModal from './components/VideoModal'
import Footer from './components/Footer'
import ConsentBanner from './components/ConsentBanner'
import { usePlaylists } from './hooks/usePlaylists'
import { useConsent } from './hooks/useConsent'

const HERO_ROTATE_MS = 9000

function App() {
  const [playlistIds, setPlaylistIds] = useState([])
  const [categories, setCategories] = useState({})
  const [configStatus, setConfigStatus] = useState('loading')
  const [selectedVideo, setSelectedVideo] = useState(null)
  const [searchQuery, setSearchQuery] = useState('')
  const [scrolled, setScrolled] = useState(false)
  const [heroIndex, setHeroIndex] = useState(0)
  const [consented, acceptConsent] = useConsent()

  // The list of playlists is shared site-wide config, managed by the site
  // owner via /admin/ (see server/README.md) — every visitor sees the same one.
  useEffect(() => {
    let cancelled = false
    fetch('/api/config.php')
      .then((res) => {
        if (!res.ok) throw new Error(`config request failed (${res.status})`)
        return res.json()
      })
      .then((data) => {
        if (cancelled) return
        setPlaylistIds(Array.isArray(data.playlistIds) ? data.playlistIds : [])
        setCategories(data.categories && typeof data.categories === 'object' ? data.categories : {})
        setConfigStatus('ready')
      })
      .catch(() => {
        if (!cancelled) setConfigStatus('error')
      })
    return () => {
      cancelled = true
    }
  }, [])

  const { playlistsState, retry } = usePlaylists(playlistIds)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40)
    window.addEventListener('scroll', onScroll)
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const orderedPlaylists = playlistIds.map((id) => ({
    id,
    status: playlistsState[id]?.status,
    error: playlistsState[id]?.error,
    meta: playlistsState[id]?.meta,
    videos: playlistsState[id]?.videos || [],
    category: categories[id] || null,
  }))

  // Group playlists by their admin-assigned category, preserving the order
  // categories first appear in. Uncategorized playlists share a null-keyed
  // group. If nobody has used categories, this collapses to a single group
  // so the homepage renders exactly as it did before the feature existed.
  const groupedPlaylists = (() => {
    const order = []
    const buckets = new Map()
    orderedPlaylists.forEach((p) => {
      const key = p.category
      if (!buckets.has(key)) {
        buckets.set(key, [])
        order.push(key)
      }
      buckets.get(key).push(p)
    })
    return order.map((key) => ({ category: key, playlists: buckets.get(key) }))
  })()

  const hasCategories = groupedPlaylists.some((g) => g.category !== null)

  const heroPool = useMemo(
    () =>
      orderedPlaylists
        .map((p) => p.videos[0])
        .filter(Boolean)
        .slice(0, 5),
    [orderedPlaylists],
  )

  useEffect(() => {
    if (heroPool.length < 2) return
    const timer = setInterval(() => {
      setHeroIndex((i) => (i + 1) % heroPool.length)
    }, HERO_ROTATE_MS)
    return () => clearInterval(timer)
  }, [heroPool.length])

  const heroVideo = heroPool[heroIndex % heroPool.length] || null

  const allVideos = useMemo(() => {
    const seen = new Map()
    orderedPlaylists.forEach((p) => {
      p.videos.forEach((v) => {
        if (!seen.has(v.id)) seen.set(v.id, v)
      })
    })
    return Array.from(seen.values())
  }, [orderedPlaylists])

  const searchResults = useMemo(() => {
    const q = searchQuery.trim().toLowerCase()
    if (!q) return null
    return allVideos.filter((v) => v.title.toLowerCase().includes(q))
  }, [searchQuery, allVideos])

  const showEmptyState = configStatus !== 'loading' && playlistIds.length === 0

  // Loading the YouTube player (hero, video cards, modal) is a "feature" of
  // this API client, so it must wait until the visitor has acknowledged the
  // YouTube API Services notice — see /privacy.html and /terms.html.
  const handleSelectVideo = (video) => {
    if (!consented) return
    setSelectedVideo(video)
  }

  return (
    <div className="app">
      <Navbar
        scrolled={scrolled || showEmptyState}
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
      />

      {configStatus === 'error' ? (
        <section className="onboarding">
          <h1>接続エラー</h1>
          <p>サーバーに接続できませんでした。時間をおいて再度お試しください。</p>
        </section>
      ) : showEmptyState ? (
        <section className="onboarding">
          <h1>ようこそ、OREFLIXへ</h1>
          <p>まだ再生リストが設定されていません。サイト管理者は /admin/ から設定してください。</p>
        </section>
      ) : searchResults ? (
        <section className="search-results">
          <h2>「{searchQuery}」の検索結果 ・ {searchResults.length}件</h2>
          {searchResults.length === 0 ? (
            <p className="search-results__empty">該当する動画が見つかりませんでした。</p>
          ) : (
            <div className="search-results__grid">
              {searchResults.map((video) => (
                <VideoCard key={video.id} video={video} onSelect={handleSelectVideo} />
              ))}
            </div>
          )}
        </section>
      ) : (
        <>
          <Hero video={heroVideo} onPlay={handleSelectVideo} />
          <main className="rows">
            {hasCategories
              ? groupedPlaylists.map((group) => (
                  <section key={group.category ?? '__uncategorized__'} className="category-group">
                    <h2 className="category-heading">{group.category ?? 'その他'}</h2>
                    <div className="category-rows">
                      {group.playlists.map((p) => (
                        <Row
                          key={p.id}
                          title={p.meta?.title || '読み込み中…'}
                          channelTitle={p.meta?.channelTitle}
                          status={p.status}
                          error={p.error}
                          videos={p.videos}
                          onSelect={handleSelectVideo}
                          onRetry={() => retry(p.id)}
                        />
                      ))}
                    </div>
                  </section>
                ))
              : orderedPlaylists.map((p) => (
                  <Row
                    key={p.id}
                    title={p.meta?.title || '読み込み中…'}
                    channelTitle={p.meta?.channelTitle}
                    status={p.status}
                    error={p.error}
                    videos={p.videos}
                    onSelect={handleSelectVideo}
                    onRetry={() => retry(p.id)}
                  />
                ))}
          </main>
        </>
      )}

      <Footer />

      {selectedVideo && (
        <VideoModal video={selectedVideo} onClose={() => setSelectedVideo(null)} />
      )}

      {!consented && <ConsentBanner onAccept={acceptConsent} />}
    </div>
  )
}

export default App
