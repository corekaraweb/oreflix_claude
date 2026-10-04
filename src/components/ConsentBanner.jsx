export default function ConsentBanner({ onAccept }) {
  return (
    <div className="consent-banner" role="dialog" aria-label="YouTube API利用に関するお知らせ">
      <p>
        このサイトはYouTube APIサービスを利用しています。動画を再生するには、
        <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener noreferrer">
          YouTube利用規約
        </a>
        、
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">
          Googleプライバシーポリシー
        </a>
        、および
        <a href="/terms.html">当サイトの利用規約</a>
        への同意が必要です。詳しくは
        <a href="/privacy.html">プライバシーポリシー</a>
        をご確認ください(同意状況は、この端末のブラウザに保存されます)。
      </p>
      <button type="button" className="btn btn--primary" onClick={onAccept}>
        同意する
      </button>
    </div>
  )
}
