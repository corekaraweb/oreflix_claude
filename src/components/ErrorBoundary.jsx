import { Component } from 'react'

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { error: null }
  }

  static getDerivedStateFromError(error) {
    return { error }
  }

  componentDidCatch(error, info) {
    // Surfaced on-screen below; also keep it in the console for devices
    // where remote inspection is available.
    console.error('OREFLIX crashed:', error, info?.componentStack)
  }

  render() {
    const { error } = this.state
    if (!error) return this.props.children

    return (
      <div className="error-screen">
        <h1>表示中にエラーが発生しました</h1>
        <p>お手数ですが、下記の内容をスクリーンショットして管理者に送ってください。</p>
        <pre>{String(error?.stack || error?.message || error)}</pre>
        <button type="button" className="btn btn--primary" onClick={() => window.location.reload()}>
          再読み込み
        </button>
      </div>
    )
  }
}
