// Ad blockers / content blockers (very common on iOS Safari & Chrome for iOS,
// via Apple's shared Content Blocker extension system) sometimes strip DOM
// nodes React still thinks it owns — most often YouTube <iframe>s. When React
// later tries to remove/reorder that node during a commit, the browser's
// native removeChild/insertBefore throws "NotFoundError", which (being
// outside a try/catch) unmounts the whole tree. These extensions are
// disabled in Private Browsing, which is why the crash only shows up there.
//
// This patches the two DOM methods to no-op instead of throwing when the
// node React is targeting isn't actually where React expects, so a
// blocker's interference degrades gracefully instead of crashing the app.
// Must run before React touches the DOM — imported first thing in main.jsx.
if (typeof Node === 'function' && Node.prototype) {
  const originalRemoveChild = Node.prototype.removeChild
  Node.prototype.removeChild = function (child) {
    if (child.parentNode !== this) {
      if (console && console.warn) {
        console.warn('[oreflix] skipped removeChild: node was already removed (likely a content blocker)', child)
      }
      return child
    }
    return originalRemoveChild.call(this, child)
  }

  const originalInsertBefore = Node.prototype.insertBefore
  Node.prototype.insertBefore = function (newNode, referenceNode) {
    if (referenceNode && referenceNode.parentNode !== this) {
      if (console && console.warn) {
        console.warn('[oreflix] skipped insertBefore: reference node was already removed (likely a content blocker)', referenceNode)
      }
      return newNode
    }
    return originalInsertBefore.call(this, newNode, referenceNode)
  }
}
