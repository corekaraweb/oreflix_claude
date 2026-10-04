const URL_PATTERN = /(https?:\/\/[^\s]+)/g

/** Turns bare URLs in plain text into clickable links (React nodes). */
export function linkify(text) {
  if (!text) return null
  return text.split(URL_PATTERN).map((part, i) =>
    i % 2 === 1 ? (
      <a key={i} href={part} target="_blank" rel="noopener noreferrer">
        {part}
      </a>
    ) : (
      part
    ),
  )
}
