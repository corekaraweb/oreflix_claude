export default function VideoCard({ video, onSelect }) {
  return (
    <button type="button" className="video-card" onClick={() => onSelect(video)}>
      <div className="video-card__media">
        <img src={video.thumbnail} alt="" loading="lazy" />
        {video.duration && <span className="video-card__duration">{video.duration}</span>}

        <div className="video-card__overlay">
          <p className="video-card__title" title={video.title}>
            {video.title}
          </p>
          <div className="video-card__meta">
            <span className="video-card__play">
              <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                <path fill="currentColor" d="M8 5v14l11-7z" />
              </svg>
            </span>
            {video.channelTitle && <span className="video-card__channel">{video.channelTitle}</span>}
          </div>
        </div>
      </div>
    </button>
  )
}
