export default function Footer() {
  return (
    <footer className="footer">
      <div className="footer__brand">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="#FF0000">
          <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
        </svg>
        <span>Powered by YouTube</span>
      </div>
      <nav className="footer__links">
        <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener noreferrer">
          YouTube利用規約
        </a>
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">
          Googleプライバシーポリシー
        </a>
        <a href="/privacy.html">プライバシーポリシー</a>
        <a href="/terms.html">利用規約</a>
      </nav>
    </footer>
  )
}
