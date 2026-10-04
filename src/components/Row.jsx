import { useEffect, useRef, useState } from 'react'
import VideoCard from './VideoCard'

function Skeleton() {
  return (
    <div className="row__track">
      {Array.from({ length: 6 }).map((_, i) => (
        <div className="video-card video-card--skeleton" key={i} />
      ))}
    </div>
  )
}

export default function Row({ title, channelTitle, status, error, videos, onSelect, onRetry }) {
  const trackRef = useRef(null)
  const [canScrollLeft, setCanScrollLeft] = useState(false)
  const [canScrollRight, setCanScrollRight] = useState(false)

  const updateScrollState = () => {
    const el = trackRef.current
    if (!el) return
    setCanScrollLeft(el.scrollLeft > 4)
    setCanScrollRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 4)
  }

  useEffect(() => {
    updateScrollState()
    window.addEventListener('resize', updateScrollState)
    return () => window.removeEventListener('resize', updateScrollState)
  }, [videos])

  const scrollBy = (dir) => {
    const el = trackRef.current
    if (!el) return
    el.scrollBy({ left: dir * el.clientWidth * 0.9, behavior: 'smooth' })
  }

  return (
    <section className="row">
      <h2 className="row__title">
        {title}
        {channelTitle && <span className="row__channel">（{channelTitle}）</span>}
      </h2>

      {status === 'error' ? (
        <div className="row__error">
          <span>動画を読み込めませんでした: {error}</span>
          <button type="button" className="btn btn--ghost" onClick={onRetry}>
            再試行
          </button>
        </div>
      ) : status === 'loading' && (!videos || videos.length === 0) ? (
        <Skeleton />
      ) : (
        <div className="row__wrapper">
          {canScrollLeft && (
            <button
              type="button"
              className="row__nav row__nav--left"
              aria-label="前へ"
              onClick={() => scrollBy(-1)}
            >
              ‹
            </button>
          )}
          <div className="row__track" ref={trackRef} onScroll={updateScrollState}>
            {videos.map((video) => (
              <VideoCard key={video.id} video={video} onSelect={onSelect} />
            ))}
            {status === 'loading-more' && (
              <div className="video-card video-card--skeleton" />
            )}
          </div>
          {canScrollRight && (
            <button
              type="button"
              className="row__nav row__nav--right"
              aria-label="次へ"
              onClick={() => scrollBy(1)}
            >
              ›
            </button>
          )}
        </div>
      )}
    </section>
  )
}
