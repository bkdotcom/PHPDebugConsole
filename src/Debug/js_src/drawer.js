import $ from 'zest'

var $root, config, origH, origPageY

/**
 * @see https://stackoverflow.com/questions/5802467/prevent-scrolling-of-parent-element-when-inner-element-scroll-position-reaches-t
 */
$.fn.scrollLock = function (enable) {
  enable = typeof enable === 'undefined'
    ? true
    : enable
  return enable
    ? enableScrollLock($(this))
    : this.off('DOMMouseScroll mousewheel wheel')
}

export function init ($debugRoot) {
  $root = $debugRoot
  config = $root.data('config')
  if (!config.get('drawer')) {
    return
  }

  $root.addClass('debug-drawer debug-enhanced-ui') // debug-enhanced-ui class is deprecated

  addMarkup()

  $root.find('.tab-panes').scrollLock()
  $root.find('.debug-resize-handle').on('mousedown', onMousedown)
  $root.find('.debug-pull-tab').on('click', open)
  $root.find('.debug-menu-bar .close').on('click', close)

  if (config.get('persistDrawer') && config.get('openDrawer')) {
    open()
  }
}

function enableScrollLock ($node) {
  $node.on('DOMMouseScroll mousewheel wheel', function (e) {
    var $this = $(this)
    var st = this.scrollTop
    var sh = this.scrollHeight
    var h = $this.innerHeight()
    var d = e.wheelDelta // was e.originalEvent.wheelDelta with jQuery
    var isUp = d > 0
    var prevent = function () {
      e.stopPropagation()
      e.preventDefault()
      e.returnValue = false
      return false
    }
    if (!isUp && -d > sh - h - st) {
      // Scrolling down, but this will take us past the bottom.
      $this.scrollTop(sh)
      return prevent()
    } else if (isUp && d > st) {
      // Scrolling up, but this will take us past the top.
      $this.scrollTop(0)
      return prevent()
    }
  })
}

function addMarkup () {
  var $menuBar = $root.find('.debug-menu-bar')
  $menuBar.before(
    '<div class="debug-pull-tab" title="Open PHPDebugConsole"><i class="fa fa-bug"></i><i class="fa fa-spinner fa-pulse"></i> PHP</div>' +
    '<div class="debug-resize-handle"></div>'
  )
  $menuBar.find('.float-right').append('<button type="button" class="close" data-dismiss="debug-drawer" aria-label="Close">' +
      '<span aria-hidden="true">&times;</span>' +
    '</button>')
}

function open (e) {
  if (e) {
    $root = $(e.target).closest('.debug-drawer')
  }
  $root.addClass('debug-drawer-open')
  $root.debugEnhance()
  setHeight() // makes sure height within min/max
  $(window).on('resize', setHeight)
  if (config.get('persistDrawer')) {
    config.set('openDrawer', true)
  }
}

function close (e) {
  if (e) {
    $root = $(e.target).closest('.debug-drawer')
  }
  $root.removeClass('debug-drawer-open')
  $(window).off('resize', setHeight)
  if (config.get('persistDrawer')) {
    config.set('openDrawer', false)
  }
  setHeight(0)
}

function onMousedown (e) {
  if (!$(e.target).closest('.debug-drawer').is('.debug-drawer-open')) {
    // drawer isn't open / ignore resize
    return
  }
  origH = $root.find('.tab-panes').height()
  origPageY = e.pageY
  $('html').addClass('debug-resizing')
  $root.parents()
    .on('mousemove', onMousemove)
    .on('mouseup', onMouseup)
  e.preventDefault()
}

function onMousemove (e) {
  var h = origH + (origPageY - e.pageY)
  setHeight(h, true)
}

function onMouseup () {
  $('html').removeClass('debug-resizing')
  $root.parents()
    .off('mousemove', onMousemove)
    .off('mouseup', onMouseup)
}

/**
 * Called on window resize or draw resize
 */
function setHeight (height, viaUser) {
  var $body = $root.find('.tab-panes')
  var menuH = $root.find('.debug-menu-bar').outerHeight()
  var minH = 20
  // inaccurate if document.doctype is null : $(window).height()
  //    aka document.documentElement.clientHeight
  var maxH = window.innerHeight - menuH - 50
  if (height === 0) {
    // debug drawer closed
    $('body').style('marginBottom', '')
    $root.trigger('resize.debug')
    return
  }
  height = checkHeight(height)
  height = Math.min(height, maxH)
  height = Math.max(height, minH)
  $body.height(height)
  $('body').style('marginBottom', ($root.height() + 8) + 'px')
  $root.trigger('resize.debug')
  if (viaUser && config.get('persistDrawer')) {
    config.set('height', height)
  }
}

function checkHeight (height) {
  var $body = $root.find('.tab-panes')
  if (height && typeof height !== 'object') {
    return height
  }
  // no height passed -> use last or 100
  height = parseInt($body[0].style.height, 10)
  if (!height && config.get('persistDrawer')) {
    height = config.get('height')
  }
  return height || 100
}
