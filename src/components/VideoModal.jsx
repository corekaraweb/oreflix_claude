import { useEffect } from 'react'
import { formatPublishedDate, formatViewCount } from '../api/youtube'
import { linkify } from '../utils/linkify'

export default function VideoModal({ video, onClose }) {
  useEffect(() => {
    const onKeyDown = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = ''
    }
  }, [onClose])

  if (!video) return null

  return (
    <div className="modal" onClick={onClose}>
      <div className="modal__box" onClick={(e) => e.stopPropagation()}>
        <div className="modal__header">
          <button type="button" className="modal__close" onClick={onClose} aria-label="閉じる">
            ×
          </button>
        </div>
        <div className="modal__player">
          <iframe
            src={`https://www.youtube.com/embed/${video.id}?autoplay=1&rel=0`}
            title={video.title}
            allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
            allowFullScreen
          />
        </div>
        <div className="modal__info">
          <h2>{video.title}</h2>
          <div className="modal__meta">
            {video.channelTitle && <span>{video.channelTitle}</span>}
            {video.viewCount != null && <span>{formatViewCount(video.viewCount)}</span>}
            {video.publishedAt && <span>{formatPublishedDate(video.publishedAt)}</span>}
          </div>
          {video.description && (
            <p className="modal__description">{linkify(video.description)}</p>
          )}
        </div>
      </div>
    </div>
  )
}
