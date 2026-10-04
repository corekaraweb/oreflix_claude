import { formatViewCount } from '../api/youtube'
import { linkify } from '../utils/linkify'

export default function Hero({ video, onPlay }) {
  if (!video) return null

  return (
    <section
      className="hero"
      style={{ backgroundImage: `url(${video.thumbnail})` }}
    >
      <div className="hero__shade" />
      <div className="hero__content">
        <h1 className="hero__title">{video.title}</h1>
        <div className="hero__meta">
          {video.channelTitle && <span>{video.channelTitle}</span>}
          {video.duration && <span>{video.duration}</span>}
          {video.viewCount != null && <span>{formatViewCount(video.viewCount)}</span>}
        </div>
        {video.description && (
          <p className="hero__description">{linkify(video.description)}</p>
        )}
        <div className="hero__actions">
          <button type="button" className="btn btn--primary" onClick={() => onPlay(video)}>
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
              <path fill="currentColor" d="M8 5v14l11-7z" />
            </svg>
            再生する
          </button>
        </div>
      </div>
    </section>
  )
}
