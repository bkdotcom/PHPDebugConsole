/**
 * handle tabs
 */

import $ from 'jquery'

export function init ($delegateNode) {
  // config = $delegateNode.data('config').get()
  var $debugTabs = $delegateNode.find('.tab-panes')
  $delegateNode.find('nav .nav-link').each(function () {
    var $tab = $(this)
    var targetSelector = $tab.data('target')
    var $tabPane = $debugTabs.find(targetSelector).eq(0)
    if ($tab.hasClass('active')) {
      // don't hide or highlight primary tab
      return // continue
    }
    if ($tabPane.text().trim().length === 0) {
      $tab.hide()
    } else if ($tabPane.find('.m_error').length) {
      $tab.addClass('has-error')
    } else if ($tabPane.find('.m_warn').length) {
      $tab.addClass('has-warn')
    } else if ($tabPane.find('.m_assert').length) {
      $tab.addClass('has-assert')
    }
  })
  $delegateNode.on('click', '[data-toggle=tab]', function () {
    show(this)
    return false
  })
  $delegateNode.on('shown.debug.tab', function (e) {
    var $target = $(e.target)
    if ($target.hasClass('string-raw')) {
      $target.debugEnhance()
      return
    }
    $target.find('.m_alert, .group-body:visible').debugEnhance()
    // highlight wasn't applied while hidden
    $target.find('.highlight').closest('.enhanced:visible').trigger('enhanced.debug')
  })
}

function show (node) {
  var $tab = $(node)
  var targetSelector = $tab.data('target')
  // .tabs-container may wrap the nav and the tabs-panes...
  var $context = (function () {
    var $tabsContainer = $tab.closest('.tabs-container')
    /*
    var $tabList = $tab.closest('nav')
    if ($tabList.data('tabPanes')) {
      // selector, dom obj obj, or jQuery obj
      return $($tabList.data('tabPanes'))
    }
    */
    return $tabsContainer.length
      ? $tabsContainer
      : $tab.closest('.debug').find('.tab-panes')
  })()
  var $tabPane = $context.find(targetSelector).eq(0)
  $tab.siblings().removeClass('active')
  $tab.addClass('active')
  $tabPane.siblings().removeClass('active')
  $tabPane.addClass('active')
  $tabPane.trigger('shown.debug.tab')
}
