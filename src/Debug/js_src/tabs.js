/**
 * handle tabs
 */

import $ from 'jquery'

export function init ($delegateNode) {
  // config = $delegateNode.data('config').get()
  var $debugTabs = $delegateNode.find('.debug-tabs')
  $delegateNode.find('nav .nav-link').each(function () {
    var $tab = $(this)
    var targetSelector = $tab.data('target')
    var $tabPane = $debugTabs.find(targetSelector)
    if ($tabPane.text().trim().length === 0) {
      $tab.hide()
    }
  })
  $delegateNode.on('click', '[data-toggle=tab]', function () {
    show(this)
    return false
  })
  $delegateNode.on('shown.debug.tab', function (e) {
    $(this).find('.m_alert, .group-body:visible').debugEnhance()
  })
}

function show (node) {
  var $tab = $(node)
  var targetSelector = $tab.data('target')
  var $debugTabs = $tab.closest('.debug').find('.debug-tabs')
  var $tabPane = $debugTabs.find(targetSelector)
  // console.log('show target', targetSelector)
  $tab.siblings().removeClass('active')
  $tab.addClass('active')
  $tabPane.siblings().removeClass('active')
  $tabPane.addClass('active')
  $tabPane.trigger('shown.debug.tab')
}

/*
function toggle (node) {
  if ($(node).hasClass("active")) {
    hide(node)
  } else {
    show(node)
  }
}

function hide (node) {
  var $tab = $(node)
  var targetSelector = $tab.data('target')
  var $debugTags = $tab.closest('.debug').find('.debug-tabs')
  var $tabPane = $debugTabs.find(targetSelector)
  console.log('hide target', targetSelector)
  $tab.removeClass('active')
  $tabPane.removeClass('active')
}
*/
