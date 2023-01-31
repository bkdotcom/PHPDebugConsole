(function ($) {
  'use strict';

  $ = $ && Object.prototype.hasOwnProperty.call($, 'default') ? $['default'] : $;

  Object.keys = Object.keys || function (o) {
    if (o !== Object(o)) {
      throw new TypeError('Object.keys called on a non-object')
    }
    var k = [];
    var p;
    for (p in o) {
      if (Object.prototype.hasOwnProperty.call(o, p)) {
        k.push(p);
      }
    }
    return k
  };

  var config;

  function init ($delegateNode) {
    config = $delegateNode.data('config').get();
    $delegateNode.on('click', '[data-toggle=vis]', function () {
      toggleVis(this);
      return false
    });
    $delegateNode.on('click', '[data-toggle=interface]', function () {
      toggleInterface(this);
      return false
    });
  }

  function addIcons ($node) {
    // console.warn('addIcons', $node)
    $.each(config.iconsObject, function (selector, v) {
      var prepend = true;
      var matches = v.match(/^([ap])\s*:(.+)$/);
      if (matches) {
        prepend = matches[1] === 'p';
        v = matches[2];
      }
      prepend
        ? $node.find(selector).prepend(v)
        : $node.find(selector).append(v);
    });
  }

  /**
   * Adds toggle icon & hides target
   * Minimal DOM manipulation -> apply to all descendants
   */
  function enhance ($node) {
    $node.find('> .classname, > .t_const').each(function () {
      var $classname = $(this);
      var $target = $classname.next();
      var isEnhanced = $classname.data('toggle') === 'object';
      if ($target.is('.t_recursion, .excluded')) {
        $classname.addClass('empty');
        return
      }
      if (isEnhanced) {
        return
      }
      if ($target.length === 0) {
        return
      }
      $classname.wrap('<span data-toggle="object"></span>')
        .after(' <i class="fa ' + config.iconsExpand.expand + '"></i>');
      $target.hide();
    });
  }

  function enhanceInner ($nodeObj) {
    var $inner = $nodeObj.find('> .object-inner');
    var accessible = $nodeObj.data('accessible');
    var hiddenInterfaces = [];
    if ($nodeObj.is('.enhanced')) {
      return
    }
    if ($inner.find('> .method[data-implements]').hide().length) {
      // linkify visibility
      $inner.find('> .method[data-implements]').each(function () {
        var iface = $(this).data('implements');
        if (hiddenInterfaces.indexOf(iface) < 0) {
          hiddenInterfaces.push(iface);
        }
      });
      $.each(hiddenInterfaces, function (i, iface) {
        $inner.find('> .interface').each(function () {
          var html = '<span class="toggle-off" data-toggle="interface" data-interface="' + iface + '" title="toggle methods">' +
              '<i class="fa fa-eye-slash"></i>' + iface + '</span>';
          if ($(this).text() === iface) {
            $(this).html(html);
          }
        });
      });
      postToggle($nodeObj);
    }
    $inner.find('> .private, > .protected')
      .filter('.magic, .magic-read, .magic-write')
      .removeClass('private protected');
    if (accessible === 'public') {
      $inner.find('.private, .protected').hide();
    }
    visToggles($inner, accessible);
    addIcons($inner);
    $inner.find('> .property.forceShow').show().find('> .t_array').debugEnhance('expand');
    $nodeObj.addClass('enhanced');
  }

  function visToggles ($inner, accessible) {
    var flags = {
      hasProtected: $inner.children('.protected').not('.magic, .magic-read, .magic-write').length > 0,
      hasPrivate: $inner.children('.private').not('.magic, .magic-read, .magic-write').length > 0,
      hasExcluded: $inner.children('.debuginfo-excluded').hide().length > 0,
      hasInherited: $inner.children('.inherited').length > 0
    };
    var toggleClass = accessible === 'public'
      ? 'toggle-off'
      : 'toggle-on';
    var toggleVerb = accessible === 'public'
      ? 'show'
      : 'hide';
    var $visToggles = $('<div class="vis-toggles"></div>');
    if (flags.hasProtected) {
      $visToggles.append('<span class="' + toggleClass + '" data-toggle="vis" data-vis="protected">' + toggleVerb + ' protected</span>');
    }
    if (flags.hasPrivate) {
      $visToggles.append('<span class="' + toggleClass + '" data-toggle="vis" data-vis="private">' + toggleVerb + ' private</span>');
    }
    if (flags.hasExcluded) {
      $visToggles.append('<span class="toggle-off" data-toggle="vis" data-vis="debuginfo-excluded">show excluded</span>');
    }
    if (flags.hasInherited) {
      $visToggles.append('<span class="toggle-on" data-toggle="vis" data-vis="inherited">hide inherited</span>');
    }
    if ($inner.find('> dt.t_modifier_final').length) {
      $inner.find('> dt.t_modifier_final').after($visToggles);
      return
    }
    $inner.prepend($visToggles);
  }

  function toggleInterface (toggle) {
    var $toggle = $(toggle);
    var iface = $toggle.data('interface');
    var $obj = $toggle.closest('.t_object');
    var $methods = $obj.find('> .object-inner > dd[data-implements=' + iface + ']');
    if ($toggle.is('.toggle-off')) {
      $toggle.addClass('toggle-on').removeClass('toggle-off');
      $methods.show();
    } else {
      $toggle.addClass('toggle-off').removeClass('toggle-on');
      $methods.hide();
    }
    postToggle($obj);
  }

  /**
   * Toggle visibility for private/protected properties and methods
   */
  function toggleVis (toggle) {
    // console.log('toggleVis', toggle)
    var $toggle = $(toggle);
    var vis = $toggle.data('vis');
    var $obj = $toggle.closest('.t_object');
    var $objInner = $obj.find('> .object-inner');
    var $toggles = $objInner.find('[data-toggle=vis][data-vis=' + vis + ']');
    var $nodes = $objInner.find('.' + vis);
    var show = $toggle.hasClass('toggle-off');
    $toggles
      .html($toggle.html().replace(
        show ? 'show ' : 'hide ',
        show ? 'hide ' : 'show '
      ))
      .addClass(show ? 'toggle-on' : 'toggle-off')
      .removeClass(show ? 'toggle-off' : 'toggle-on');
    show
      ? toggleVisNodes($nodes) // show for this and all descendants
      : $nodes.hide(); // hide for this and all descendants
    postToggle($obj, true);
  }

  function toggleVisNodes ($nodes) {
    $nodes.each(function () {
      var $node = $(this);
      var $objInner = $node.closest('.object-inner');
      var show = true;
      $objInner.find('> .vis-toggles [data-toggle]').each(function () {
        var $toggle = $(this);
        var vis = $toggle.data('vis');
        var isOn = $toggle.is('.toggle-on');
        // if any applicable test is false, don't show it
        if (!isOn && $node.hasClass(vis)) {
          show = false;
          return false // break
        }
      });
      if (show) {
        $node.show();
      }
    });
  }

  function postToggle ($obj, allDescendants) {
    var selector = allDescendants
      ? '.object-inner > dt'
      : '> .object-inner > dt';
    $obj.find(selector).each(function (i, dt) {
      var $dds = $(dt).nextUntil('dt');
      var $ddsVis = $dds.filter(function (index, node) {
        return $(node).css('display') !== 'none'
      });
      $(dt).toggleClass('text-muted', $dds.length > 0 && $ddsVis.length === 0);
    });
  }

  /**
   * Add sortability to given table
   */
  function makeSortable (table) {
    var $table = $(table);
    var $head = $table.find('> thead');
    if (!$table.is('table.sortable')) {
      return $table
    }
    $table.addClass('table-sort');
    $head.on('click', 'th', function () {
      var $th = $(this);
      var $cells = $(this).closest('tr').children();
      var i = $cells.index($th);
      var curDir = $th.is('.sort-asc') ? 'asc' : 'desc';
      var newDir = curDir === 'desc' ? 'asc' : 'desc';
      $cells.removeClass('sort-asc sort-desc');
      $th.addClass('sort-' + newDir);
      if (!$th.find('.sort-arrows').length) {
        // this th needs the arrow markup
        $cells.find('.sort-arrows').remove();
        $th.append('<span class="fa fa-stack sort-arrows float-right">' +
            '<i class="fa fa-caret-up" aria-hidden="true"></i>' +
            '<i class="fa fa-caret-down" aria-hidden="true"></i>' +
          '</span>');
      }
      sortTable($table[0], i, newDir);
    });
    return $table
  }

  /**
   * Sort table
   *
   * @param obj table dom element
   * @param int col   column index
   * @param str dir   (asc) or desc
   */
  function sortTable (table, col, dir) {
    var body = table.tBodies[0];
    var rows = body.rows;
    var i;
    var collator = typeof Intl.Collator === 'function'
      ? new Intl.Collator([], {
        numeric: true,
        sensitivity: 'base'
      })
      : null;
    dir = dir === 'desc' ? -1 : 1;
    rows = Array.prototype.slice.call(rows, 0); // Converts HTMLCollection to Array
    rows = rows.sort(rowComparator(col, dir, collator));
    for (i = 0; i < rows.length; ++i) {
      body.appendChild(rows[i]); // append each row in order (which moves)
    }
  }

  function rowComparator (col, dir, collator) {
    var floatRe = /^([+-]?(?:0|[1-9]\d*)(?:\.\d*)?)(?:[eE]([+-]?\d+))?$/;
    return function sortFunction (trA, trB) {
      var a = trA.cells[col].textContent.trim();
      var b = trB.cells[col].textContent.trim();
      var afloat = a.match(floatRe);
      var bfloat = b.match(floatRe);
      var comp = 0;
      if (afloat) {
        a = toFixed(a, afloat);
      }
      if (bfloat) {
        b = toFixed(b, bfloat);
      }
      if (afloat && bfloat) {
        if (a < b) {
          comp = -1;
        } else if (a > b) {
          comp = 1;
        }
        return dir * comp
      }
      comp = collator
        ? collator.compare(a, b)
        : a.localeCompare(b); // not a natural sort
      return dir * comp
    }
  }

  function toFixed (str, matches) {
    var num = Number.parseFloat(str);
    if (matches[2]) {
      // sci notation
      num = num.toFixed(6);
    }
    return num
  }

  var config$1;

  function init$1 ($root) {
    config$1 = $root.data('config').get();
    $root.on('config.debug.updated', function (e, changedOpt) {
      e.stopPropagation();
      if (changedOpt === 'linkFilesTemplate') {
        update($root);
      }
    });
  }

  /**
   * Linkify files if not already done or update already linked files
   */
  function update ($group) {
    var remove = !config$1.linkFiles || config$1.linkFilesTemplate.length === 0;
    $group.find('li[data-detect-files]').each(function () {
      create($(this), $(this).find('.t_string'), remove);
    });
  }

  /**
   * Create text editor links for error, warn, & trace
   */
  function create ($entry, $strings, remove) {
    var $objects = $entry.find('.t_object > .object-inner > .property.debug-value > .t_identifier').filter(function () {
      return this.innerText.match(/^file$/)
    });
    var detectFiles = $entry.data('detectFiles') === true || $objects.length > 0;
    if (!config$1.linkFiles && !remove) {
      return
    }
    if (detectFiles === false) {
      return
    }
    // console.warn('createFileLinks', remove, $entry[0], $strings)
    if ($entry.is('.m_trace')) {
      createFileLinksTrace($entry, remove);
      return
    }
    // don't remove data... link template may change
    // $entry.removeData('detectFiles foundFiles')
    if ($entry.is('[data-file]')) {
      /*
        Log entry link
      */
      createFileLinkDataFile($entry, remove);
      return
    }
    createFileLinksStrings($entry, $strings, remove);
  }

  function buildFileLink (file, line) {
    var data = {
      file: file,
      line: line || 1
    };
    return config$1.linkFilesTemplate.replace(
      /%(\w*)\b/g,
      function (m, key) {
        return Object.prototype.hasOwnProperty.call(data, key)
          ? data[key]
          : ''
      }
    )
  }

  function createFileLinksStrings ($entry, $strings, remove) {
    var dataFoundFiles = $entry.data('foundFiles') || [];
    if ($entry.is('.m_table')) {
      $strings = $entry.find('> table > tbody > tr > .t_string');
    }
    if (!$strings) {
      $strings = [];
    }
    $.each($strings, function () {
      createFileLink(this, remove, dataFoundFiles);
    });
  }

  function createFileLinkDataFile ($entry, remove) {
    $entry.find('> .file-link').remove();
    if (remove) {
      return
    }
    $entry.append($('<a>', {
      html: '<i class="fa fa-external-link"></i>',
      href: buildFileLink($entry.data('file'), $entry.data('line')),
      title: 'Open in editor',
      class: 'file-link lpad'
    })[0].outerHTML);
  }

  function createFileLinksTrace ($entry, remove) {
    var isUpdate = $entry.find('.file-link').length > 0;
    if (!isUpdate) {
      $entry.find('table thead tr > *:last-child').after('<th></th>');
    } else if (remove) {
      $entry.find('table tr > *:last-child').remove();
      return
    }
    $entry.find('table tbody tr').each(function () {
      var $tr = $(this);
      var $tds = $tr.find('> td');
      var $a = $('<a>', {
        class: 'file-link',
        href: buildFileLink($tds.eq(0).text(), $tds.eq(1).text()),
        html: '<i class="fa fa-fw fa-external-link"></i>',
        style: 'vertical-align: bottom',
        title: 'Open in editor'
      });
      if (isUpdate) {
        $tr.find('.file-link').replaceWith($a);
        return // continue
      }
      if ($tr.hasClass('context')) {
        $tds.eq(0).attr('colspan', parseInt($tds.eq(0).attr('colspan'), 10) + 1);
        return // continue
      }
      $tds.last().after($('<td/>', {
        class: 'text-center',
        html: $a
      }));
    });
  }

  function createFileLink (string, remove, foundFiles) {
    // console.log('createFileLink', $(string).text())
    var $replace;
    var $string = $(string);
    var attrs = string.attributes;
    var html = $.trim($string.html());
    var matches = createFileLinkMatches($string, foundFiles);
    if ($string.closest('.m_trace').length) {
      // not recurssion...  will end up calling createFileLinksTrace
      create($string.closest('.m_trace'));
      return
    }
    if (!matches.length) {
      return
    }
    $replace = remove || $string.find('.fa-external-link').length
      ? $('<span>', {
        html: html
      })
      : $('<a>', {
        class: 'file-link',
        href: buildFileLink(matches[1], matches[2]),
        html: html + ' <i class="fa fa-external-link"></i>',
        title: 'Open in editor'
      });
    /*
      attrs is not a plain object, but an array of attribute nodes
      which contain both the name and value
    */
    $.each(attrs, function () {
      var name = this.name;
      if (['html', 'href', 'title'].indexOf(name) > -1) {
        return // continue
      }
      if (name === 'class') {
        $replace.addClass(this.value);
        return // continue
      }
      $replace.attr(name, this.value);
    });
    if ($string.is('td, th, li')) {
      $string.html(remove
        ? html
        : $replace
      );
      return
    }
    $string.replaceWith($replace);
  }

  function createFileLinkMatches ($string, foundFiles) {
    var matches = [];
    var html = $.trim($string.html());
    if ($string.data('file')) {
      // filepath specified in data-file attr
      return typeof $string.data('file') === 'boolean'
        ? [null, html, 1]
        : [null, $string.data('file'), $string.data('line') || 1]
    }
    if (foundFiles.indexOf(html) === 0) {
      return [null, html, 1]
    }
    if ($string.parent('.property.debug-value').find('> .t_identifier').text().match(/^file$/)) {
      // object with file .debug-value
      matches = {
        line: 1
      };
      $string.parent().parent().find('> .property.debug-value').each(function () {
        var prop = $(this).find('> .t_identifier')[0].innerText;
        var $valNode = $(this).find('> *:last-child');
        var val = $.trim($valNode[0].innerText);
        matches[prop] = val;
      });
      return [null, html, matches.line]
    }
    return html.match(/^(\/.+\.php)(?: \(line (\d+)\))?$/) || []
  }

  var config$2;
  var toExpandQueue = [];
  var processingQueue = false;

  function init$2 ($root) {
    config$2 = $root.data('config').get();
    init($root);
    init$1($root);
    $root.on('click', '.close[data-dismiss=alert]', function () {
      $(this).parent().remove();
    });
    $root.on('click', '.show-more-container .show-less', onClickShowLess);
    $root.on('click', '.show-more-container .show-more', onClickShowMore);
    $root.on('expand.debug.array', onExpandArray);
    $root.on('expand.debug.group', onExpandGroup);
    $root.on('expand.debug.object', onExpandObject);
    $root.on('expanded.debug.next', '.context', function (e) {
      enhanceArray($(e.target).find('> td > .t_array'));
    });
    $root.on('expanded.debug.array expanded.debug.group expanded.debug.object', onExpanded);
  }

  /**
   * Enhance log entries inside .group-body
   */
  function enhanceEntries ($node) {
    // console.log('enhanceEntries', $node[0])
    var $parent = $node.parent();
    var show = !$parent.hasClass('m_group') || $parent.hasClass('expanded');
    // temporarily hide when enhancing... minimize redraws
    $node.hide();
    $node.children().each(function () {
      enhanceEntry($(this));
    });
    if (show) {
      $node.show().trigger('expanded.debug.group');
    }
    processExpandQueue();
    if ($node.parent().hasClass('m_group') === false) {
      // only add .enhanced to root .group-body
      $node.addClass('enhanced');
    }
  }

  /**
   * Enhance a single log entry
   * we don't enhance strings by default (add showmore).. needs to be visible to calc height
   */
  function enhanceEntry ($entry) {
    // console.log('enhanceEntry', $entry[0])
    if ($entry.hasClass('enhanced')) {
      return
    } else if ($entry.hasClass('m_group')) {
      enhanceGroup($entry);
    } else if ($entry.hasClass('filter-hidden')) {
      return
    } else if ($entry.is('.m_table, .m_trace')) {
      enhanceEntryTabular($entry);
    } else {
      enhanceEntryDefault($entry);
    }
    $entry.addClass('enhanced');
    $entry.trigger('enhanced.debug');
  }

  function enhanceValue ($entry, node) {
    var $node = $(node);
    if ($node.is('.t_array')) {
      enhanceArray($node);
    } else if ($node.is('.t_object')) {
      enhance($node);
    } else if ($node.is('table')) {
      makeSortable($node);
    } else if ($node.is('.t_string')) {
      create($entry, $node);
    } else if ($node.is('.string-encoded.tabs-container')) {
      // console.warn('enhanceStringEncoded', $node)
      enhanceValue($node, $node.find('> .tab-pane.active > *'));
    }
  }

  function onClickShowLess () {
    var $container = $(this).closest('.show-more-container');
    $container.find('.show-more-wrapper')
      .css('display', 'block')
      .animate({
        height: '70px'
      });
    $container.find('.show-more-fade').fadeIn();
    $container.find('.show-more').show();
    $container.find('.show-less').hide();
  }

  function onClickShowMore () {
    var $container = $(this).closest('.show-more-container');
    $container.find('.show-more-wrapper').animate({
      height: $container.find('.t_string').height()
    }, 400, 'swing', function () {
      $(this).css('display', 'inline');
    });
    $container.find('.show-more-fade').fadeOut();
    $container.find('.show-more').hide();
    $container.find('.show-less').show();
  }

  function onExpandArray (e) {
    var $node = $(e.target); // .t_array
    var $entry = $node.closest('li[class*=m_]');
    e.stopPropagation();
    $node.find('> .array-inner > li > :last-child, > .array-inner > li[class]').each(function () {
      enhanceValue($entry, this);
    });
  }

  function onExpandGroup (e) {
    var $node = $(e.target); // .m_group
    e.stopPropagation();
    $node.find('> .group-body').debugEnhance();
  }

  function onExpandObject (e) {
    var $node = $(e.target); // .t_object
    var $entry = $node.closest('li[class*=m_]');
    e.stopPropagation();
    if ($node.is('.enhanced')) {
      return
    }
    $node.find('> .object-inner')
      .find('> .constant > :last-child,' +
        '> .property > :last-child,' +
        '> .method .t_string'
      ).each(function () {
        enhanceValue($entry, this);
      });
    enhanceInner($node);
  }

  function onExpanded (e) {
    var $strings;
    var $target = $(e.target);
    if ($target.hasClass('t_array')) {
      // e.namespace = array.debug ??
      $strings = $target.find('> .array-inner')
        .find('> li > .t_string,' +
          ' > li.t_string');
    } else if ($target.hasClass('m_group')) {
      // e.namespace = debug.group
      $strings = $target.find('> .group-body > li > .t_string');
    } else if ($target.hasClass('t_object')) {
      // e.namespace = debug.object
      $strings = $target.find('> .object-inner')
        .find('> dd.constant > .t_string,' +
          ' > dd.property:visible > .t_string,' +
          ' > dd.method > .t_string');
    } else {
      $strings = $();
    }
    $strings.not('[data-type-more=numeric]').each(function () {
      enhanceLongString($(this));
    });
  }

  /**
   * add font-awsome icons
   */
  function addIcons$1 ($node) {
    var $caption;
    var $icon = determineIcon($node);
    addIconsMisc($node);
    if (!$icon) {
      return
    }
    if ($node.hasClass('m_group')) {
      // custom icon..   add to .group-label
      $node = $node.find('> .group-header .group-label').eq(0);
    } else if ($node.find('> table').length) {
      // table... we'll prepend icon to caption
      $caption = $node.find('> table > caption');
      if (!$caption.length) {
        $caption = $('<caption>');
        $node.find('> table').prepend($caption);
      }
      $node = $caption;
    }
    if ($node.find('> i:first-child').hasClass($icon.attr('class'))) {
      // already have icon
      return
    }
    $node.prepend($icon);
  }

  function addIconsMisc ($node) {
    var $icon;
    var $node2;
    var selector;
    for (selector in config$2.iconsMisc) {
      $node2 = $node.find(selector);
      if ($node2.length === 0) {
        continue
      }
      $icon = $(config$2.iconsMisc[selector]);
      if ($node2.find('> i:first-child').hasClass($icon.attr('class'))) {
        // already have icon
        $icon = null;
        continue
      }
      $node2.prepend($icon);
      $icon = null;
    }
  }

  function determineIcon ($node) {
    var $icon;
    var $node2;
    var selector;
    if ($node.data('icon')) {
      return $node.data('icon').match('<')
        ? $($node.data('icon'))
        : $('<i>').addClass($node.data('icon'))
    }
    if ($node.hasClass('m_group')) {
      return $icon
    }
    $node2 = $node.hasClass('group-header')
      ? $node.parent()
      : $node;
    for (selector in config$2.iconsMethods) {
      if ($node2.is(selector)) {
        $icon = $(config$2.iconsMethods[selector]);
        break
      }
    }
    return $icon
  }

  /**
   * Adds expand/collapse functionality to array
   * does not enhance values
   */
  function enhanceArray ($node) {
    // console.log('enhanceArray', $node[0])
    var $arrayInner = $node.find('> .array-inner');
    var isEnhanced = $node.find(' > .t_array-expand').length > 0;
    if (isEnhanced) {
      return
    }
    if ($.trim($arrayInner.html()).length < 1) {
      // empty array -> don't add expand/collapse
      $node.addClass('expanded').find('br').hide();
      if ($node.hasClass('max-depth') === false) {
        return
      }
    }
    enhanceArrayAddMarkup($node);
    $.each(config$2.iconsArray, function (selector, v) {
      $node.find(selector).prepend(v);
    });
    $node.debugEnhance(enhanceArrayIsExpanded($node) ? 'expand' : 'collapse');
  }

  function enhanceArrayAddMarkup ($node) {
    var $arrayInner = $node.find('> .array-inner');
    var $expander;
    if ($node.closest('.array-file-tree').length) {
      $node.find('> .t_keyword, > .t_punct').remove();
      $arrayInner.find('> li > .t_operator, > li > .t_key.t_int').remove();
      $node.prevAll('.t_key').each(function () {
        var $dir = $(this).attr('data-toggle', 'array');
        $node.prepend($dir);
        $node.prepend(
          '<span class="t_array-collapse" data-toggle="array">▾ </span>' + // ▼
          '<span class="t_array-expand" data-toggle="array">▸ </span>' // ▶
        );
      });
      return
    }
    $expander = $('<span class="t_array-expand" data-toggle="array">' +
        '<span class="t_keyword">array</span><span class="t_punct">(</span> ' +
        '<i class="fa ' + config$2.iconsExpand.expand + '"></i>&middot;&middot;&middot; ' +
        '<span class="t_punct">)</span>' +
      '</span>');
    // add expand/collapse
    $node.find('> .t_keyword').first()
      .wrap('<span class="t_array-collapse" data-toggle="array">')
      .after('<span class="t_punct">(</span> <i class="fa ' + config$2.iconsExpand.collapse + '"></i>')
      .parent().next().remove(); // remove original '('
    $node.prepend($expander);
  }

  function enhanceArrayIsExpanded ($node) {
    var expand = $node.data('expand');
    var numParents = $node.parentsUntil('.m_group', '.t_object, .t_array').length;
    var expandDefault = numParents === 0;
    if (expand === undefined && numParents !== 0) {
      // nested array and expand === undefined
      expand = $node.closest('.t_array[data-expand]').data('expand');
    }
    if (expand === undefined) {
      expand = expandDefault;
    }
    return expand || $node.hasClass('array-file-tree')
  }

  function enhanceEntryDefault ($entry) {
    // regular log-type entry
    if ($entry.data('file')) {
      if (!$entry.attr('title')) {
        $entry.attr('title', $entry.data('file') + ': line ' + $entry.data('line'));
      }
      create($entry);
    }
    addIcons$1($entry);
    $entry.children().each(function () {
      enhanceValue($entry, this);
    });
  }

  function enhanceEntryTabular ($entry) {
    create($entry);
    addIcons$1($entry);
    if ($entry.hasClass('m_table')) {
      $entry.find('> table > tbody > tr > td').each(function () {
        enhanceValue($entry, this);
      });
    }
    // table may have a expand collapse row that's initially expanded
    //   trigger expanded event  (so, trace context args are enhanced, etc)
    $entry.find('tbody > tr.expanded').next().trigger('expanded.debug.next');
    makeSortable($entry.find('> table'));
  }

  function enhanceGroup ($group) {
    // console.log('enhanceGroup', $group[0])
    var $toggle = $group.find('> .group-header');
    var $target = $toggle.next();
    addIcons$1($group); // custom data-icon
    addIcons$1($toggle); // expand/collapse
    $toggle.attr('data-toggle', 'group');
    $toggle.find('.t_array, .t_object').each(function () {
      $(this).data('expand', false);
      enhanceValue($group, this);
    });
    $.each(['level-error', 'level-info', 'level-warn'], function (i, classname) {
      var $toggleIcon;
      if ($group.hasClass(classname)) {
        $toggleIcon = $toggle.children('i').eq(0);
        $toggle.wrapInner('<span class="' + classname + '"></span>');
        $toggle.prepend($toggleIcon); // move icon
      }
    });
    /*
    if ($group.hasClass('filter-hidden')) {
      return
    }
    */
    if (
      $group.hasClass('expanded') ||
      $target.find('.m_error, .m_warn').not('.filter-hidden').not('[data-uncollapse=false]').length
    ) {
      toExpandQueue.push($toggle);
      return
    }
    $toggle.debugEnhance('collapse', true);
  }

  function enhanceLongString ($node) {
    var $container;
    var $stringWrap;
    var height = $node.height();
    var diff = height - 70;
    if (diff > 35) {
      $stringWrap = $node.wrap('<div class="show-more-wrapper"></div>').parent();
      $stringWrap.append('<div class="show-more-fade"></div>');
      $container = $stringWrap.wrap('<div class="show-more-container"></div>').parent();
      $container.append('<button type="button" class="show-more"><i class="fa fa-caret-down"></i> More</button>');
      $container.append('<button type="button" class="show-less" style="display:none;"><i class="fa fa-caret-up"></i> Less</button>');
    }
  }

  function processExpandQueue () {
    if (processingQueue) {
      return
    }
    processingQueue = true;
    while (toExpandQueue.length) {
      toExpandQueue.shift().debugEnhance('expand');
    }
    processingQueue = false;
  }

  var $root, config$3, origH, origPageY;

  /**
   * @see https://stackoverflow.com/questions/5802467/prevent-scrolling-of-parent-element-when-inner-element-scroll-position-reaches-t
   */
  $.fn.scrollLock = function (enable) {
    enable = typeof enable === 'undefined'
      ? true
      : enable;
    return enable
      ? enableScrollLock($(this))
      : this.off('DOMMouseScroll mousewheel wheel')
  };

  function init$3 ($debugRoot) {
    $root = $debugRoot;
    config$3 = $root.data('config');
    if (!config$3.get('drawer')) {
      return
    }

    $root.addClass('debug-drawer debug-enhanced-ui'); // debug-enhanced-ui class is deprecated

    addMarkup();

    $root.find('.tab-panes').scrollLock();
    $root.find('.debug-resize-handle').on('mousedown', onMousedown);
    $root.find('.debug-pull-tab').on('click', open);
    $root.find('.debug-menu-bar .close').on('click', close);

    if (config$3.get('persistDrawer') && config$3.get('openDrawer')) {
      open();
    }
  }

  function enableScrollLock ($node) {
    $node.on('DOMMouseScroll mousewheel wheel', function (e) {
      var $this = $(this);
      var st = this.scrollTop;
      var sh = this.scrollHeight;
      var h = $this.innerHeight();
      var d = e.originalEvent.wheelDelta;
      var isUp = d > 0;
      var prevent = function () {
        e.stopPropagation();
        e.preventDefault();
        e.returnValue = false;
        return false
      };
      if (!isUp && -d > sh - h - st) {
        // Scrolling down, but this will take us past the bottom.
        $this.scrollTop(sh);
        return prevent()
      } else if (isUp && d > st) {
        // Scrolling up, but this will take us past the top.
        $this.scrollTop(0);
        return prevent()
      }
    });
  }

  function addMarkup () {
    var $menuBar = $root.find('.debug-menu-bar');
    $menuBar.before(
      '<div class="debug-pull-tab" title="Open PHPDebugConsole"><i class="fa fa-bug"></i><i class="fa fa-spinner fa-pulse"></i> PHP</div>' +
      '<div class="debug-resize-handle"></div>'
    );
    $menuBar.find('.float-right').append('<button type="button" class="close" data-dismiss="debug-drawer" aria-label="Close">' +
        '<span aria-hidden="true">&times;</span>' +
      '</button>');
  }

  function open () {
    $root.addClass('debug-drawer-open');
    $root.debugEnhance();
    setHeight(); // makes sure height within min/max
    $('body').css('marginBottom', ($root.height() + 8) + 'px');
    $(window).on('resize', setHeight);
    if (config$3.get('persistDrawer')) {
      config$3.set('openDrawer', true);
    }
  }

  function close () {
    $root.removeClass('debug-drawer-open');
    $('body').css('marginBottom', '');
    $(window).off('resize', setHeight);
    if (config$3.get('persistDrawer')) {
      config$3.set('openDrawer', false);
    }
  }

  function onMousemove (e) {
    var h = origH + (origPageY - e.pageY);
    setHeight(h, true);
  }

  function onMousedown (e) {
    if (!$(e.target).closest('.debug-drawer').is('.debug-drawer-open')) {
      // drawer isn't open / ignore resize
      return
    }
    origH = $root.find('.tab-panes').height();
    origPageY = e.pageY;
    $('html').addClass('debug-resizing');
    $root.parents()
      .on('mousemove', onMousemove)
      .on('mouseup', onMouseup);
    e.preventDefault();
  }

  function onMouseup () {
    $('html').removeClass('debug-resizing');
    $root.parents()
      .off('mousemove', onMousemove)
      .off('mouseup', onMouseup);
    $('body').css('marginBottom', ($root.height() + 8) + 'px');
  }

  function setHeight (height, viaUser) {
    var $body = $root.find('.tab-panes');
    var menuH = $root.find('.debug-menu-bar').outerHeight();
    var minH = 20;
    // inacurate if document.doctype is null : $(window).height()
    //    aka document.documentElement.clientHeight
    var maxH = window.innerHeight - menuH - 50;
    height = checkHeight(height);
    height = Math.min(height, maxH);
    height = Math.max(height, minH);
    $body.css('height', height);
    if (viaUser && config$3.get('persistDrawer')) {
      config$3.set('height', height);
    }
  }

  function checkHeight (height) {
    var $body = $root.find('.tab-panes');
    if (height && typeof height !== 'object') {
      return height
    }
    // no height passed -> use last or 100
    height = parseInt($body[0].style.height, 10);
    if (!height && config$3.get('persistDrawer')) {
      height = config$3.get('height');
    }
    return height || 100
  }

  /**
   * Filter entries
   */

  var channels = [];
  var tests = [
    function ($node) {
      var channel = $node.data('channel') || $node.closest('.debug').data('channelNameRoot');
      return channels.indexOf(channel) > -1
    }
  ];
  var preFilterCallbacks = [
    function ($root) {
      var $checkboxes = $root.find('input[data-toggle=channel]');
      if ($checkboxes.length === 0) {
        channels = [$root.data('channelNameRoot')];
        return
      }
      channels = [];
      $checkboxes.filter(':checked').each(function () {
        channels.push($(this).val());
      });
    }
  ];

  function init$4 ($delegateNode) {
    /*
    var $debugTabLog = $delegateNode.find('> .tab-panes > .tab-primary')
    if ($debugTabLog.length > 0 && $debugTabLog.data('options').sidebar === false) {
      // no sidebar -> no filtering
      //    documentation uses non-sidebar filtering
      return
    }
    */
    applyFilter($delegateNode);
    $delegateNode.on('change', 'input[type=checkbox]', onCheckboxChange);
    $delegateNode.on('change', 'input[data-toggle=error]', onToggleErrorChange);
    $delegateNode.on('channelAdded.debug', function (e) {
      var $root = $(e.target).closest('.debug');
      updateFilterStatus($root);
    });
  }

  function onCheckboxChange () {
    var $this = $(this);
    var isChecked = $this.is(':checked');
    var $nested = $this.closest('label').next('ul').find('input');
    var $root = $this.closest('.debug');
    if ($this.data('toggle') === 'error') {
      // filtered separately
      return
    }
    $nested.prop('checked', isChecked);
    applyFilter($root);
    updateFilterStatus($root);
  }

  function onToggleErrorChange () {
    var $this = $(this);
    var isChecked = $this.is(':checked');
    var $root = $this.closest('.debug');
    var errorClass = $this.val();
    var selector = '.group-body .error-' + errorClass;
    $root.find(selector).toggleClass('filter-hidden', !isChecked);
    // trigger collapse to potentially update group icon and add/remove empty class
    $root.find('.m_error, .m_warn').parents('.m_group')
      .trigger('collapsed.debug.group');
    updateFilterStatus($root);
  }

  function addTest (func) {
    tests.push(func);
  }

  function addPreFilter (func) {
    preFilterCallbacks.push(func);
  }

  function applyFilter ($root) {
    var channelNameRoot = $root.data('channelNameRoot');
    var i;
    var len;
    var sort = [];
    for (i in preFilterCallbacks) {
      preFilterCallbacks[i]($root);
    }
    /*
      find all log entries and process them greatest depth to least depth
    */
    $root.find('> .tab-panes > .tab-primary > .tab-body')
      .find('.m_alert, .group-body > *:not(.m_groupSummary)')
      .each(function () {
        sort.push({
          depth: $(this).parentsUntil('.tab_body').length,
          node: $(this)
        });
      });
    sort.sort(function (a, b) {
      return a.depth < b.depth ? 1 : -1
    });
    for (i = 0, len = sort.length; i < len; i++) {
      var $node = sort[i].node;
      applyFilterToNode($node, channelNameRoot);
    }
  }

  function applyFilterToNode ($node, channelNameRoot) {
    var hiddenWas = $node.is('.filter-hidden');
    var i;
    var isFilterVis = true;
    var $parentGroup;
    if ($node.data('channel') === channelNameRoot + '.phpError') {
      // php Errors are filtered separately
      return
    }
    for (i in tests) {
      isFilterVis = tests[i]($node);
      if (!isFilterVis) {
        break
      }
    }
    $node.toggleClass('filter-hidden', !isFilterVis);
    if (isFilterVis && hiddenWas) {
      // unhiding
      $parentGroup = $node.parent().closest('.m_group');
      if (!$parentGroup.length || $parentGroup.hasClass('expanded')) {
        $node.debugEnhance();
      }
    } else if (!isFilterVis && !hiddenWas) {
      // hiding
      if ($node.hasClass('m_group')) {
        // filtering group... means children (if not filtered) are visible
        $node.find('> .group-body').debugEnhance();
      }
    }
    if (isFilterVis && $node.hasClass('m_group')) {
      $node.trigger('collapsed.debug.group');
    }
  }

  function updateFilterStatus ($debugRoot) {
    var haveUnchecked = $debugRoot.find('.debug-sidebar input:checkbox:not(:checked)').length > 0;
    $debugRoot.toggleClass('filter-active', haveUnchecked);
  }

  function cookieGet (name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    var c = null;
    var i = 0;
    for (i = 0; i < ca.length; i += 1) {
      c = ca[i];
      while (c.charAt(0) === ' ') {
        c = c.substring(1, c.length);
      }
      if (c.indexOf(nameEQ) === 0) {
        return c.substring(nameEQ.length, c.length)
      }
    }
    return null
  }

  function cookieRemove (name) {
    cookieSet(name, '', -1);
  }

  function cookieSet (name, value, days) {
    // console.log('cookieSet', name, value, days)
    var expires = '';
    var date = new Date();
    if (days) {
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      expires = '; expires=' + date.toGMTString();
    }
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
  }

  function lsGet (key) {
    var path = key.split('.', 2);
    var val = window.localStorage.getItem(path[0]);
    if (typeof val !== 'string' || val.length < 1) {
      return null
    }
    try {
      val = JSON.parse(val);
    } catch (e) {
    }
    return path.length > 1
      ? val[path[1]]
      : val
  }

  function lsSet (key, val) {
    var path = key.split('.', 2);
    var lsVal;
    key = path[0];
    if (path.length > 1) {
      lsVal = lsGet(key) || {};
      lsVal[path[1]] = val;
      val = lsVal;
    }
    if (val === null) {
      localStorage.removeItem(key);
      return
    }
    if (typeof val !== 'string') {
      val = JSON.stringify(val);
    }
    window.localStorage.setItem(key, val);
  }

  function queryDecode (qs) {
    var params = {};
    var tokens;
    var re = /[?&]?([^&=]+)=?([^&]*)/g;
    if (qs === undefined) {
      qs = document.location.search;
    }
    qs = qs.split('+').join(' '); // replace + with ' '
    while (true) {
      tokens = re.exec(qs);
      if (!tokens) {
        break
      }
      params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }
    return params
  }

  var $root$1;
  var config$4;
  var KEYCODE_ESC = 27;

  function init$5 ($debugRoot) {
    $root$1 = $debugRoot;
    config$4 = $root$1.data('config');

    addDropdown();

    $root$1.find('.debug-options-toggle')
      .on('click', onDebugOptionsToggle);

    $('input[name=debugCookie]')
      .on('change', onDebugCookieChange)
      .prop('checked', config$4.get('debugKey') && cookieGet('debug') === config$4.get('debugKey'));
    if (!config$4.get('debugKey')) {
      $('input[name=debugCookie]').prop('disabled', true)
        .closest('label').addClass('disabled');
    }

    $('input[name=persistDrawer]')
      .on('change', onPersistDrawerChange)
      .prop('checked', config$4.get('persistDrawer'));

    $root$1.find('input[name=linkFiles]')
      .on('change', onLinkFilesChange)
      .prop('checked', config$4.get('linkFiles')).trigger('change');

    $root$1.find('input[name=linkFilesTemplate]')
      .on('change', onLinkFilesTemplateChange)
      .val(config$4.get('linkFilesTemplate'));
  }

  function addDropdown () {
    var $menuBar = $root$1.find('.debug-menu-bar');
    var id = $('.debug-options').length + 1;
    $menuBar.find('.float-right').prepend('<button class="debug-options-toggle" type="button" data-toggle="debug-options" aria-label="Options" aria-haspopup="true" aria-expanded="false">' +
        '<i class="fa fa-ellipsis-v fa-fw"></i>' +
      '</button>'
    );
    $menuBar.append('<div class="debug-options" aria-labelledby="debug-options-toggle">' +
        '<div class="debug-options-body">' +
          '<label><input type="checkbox" name="debugCookie" /> Debug Cookie</label>' +
          '<label><input type="checkbox" name="persistDrawer" /> Keep Open/Closed</label>' +
          '<label><input type="checkbox" name="linkFiles" /> Create file links</label>' +
          '<div class="form-group">' +
            '<label for="linkFilesTemplate_' + id + '">Link Template</label>' +
            '<input id="linkFilesTemplate_' + id + '" name="linkFilesTemplate" />' +
          '</div>' +
          '<hr class="dropdown-divider" />' +
          '<a href="http://www.bradkent.com/php/debug" target="_blank">Documentation</a>' +
        '</div>' +
      '</div>'
    );
    if (!config$4.get('drawer')) {
      $menuBar.find('input[name=persistDrawer]').closest('label').remove();
    }
  }

  function onBodyClick (e) {
    if ($root$1.find('.debug-options').find(e.target).length === 0) {
      // we clicked outside the dropdown
      close$1();
    }
  }

  function onBodyKeyup (e) {
    if (e.keyCode === KEYCODE_ESC) {
      close$1();
    }
  }

  function onDebugCookieChange () {
    var isChecked = $(this).is(':checked');
    isChecked
      ? cookieSet('debug', config$4.get('debugKey'), 7)
      : cookieRemove('debug');
  }

  function onDebugOptionsToggle (e) {
    var isVis = $(this).closest('.debug-bar').find('.debug-options').is('.show');
    $root$1 = $(this).closest('.debug');
    isVis
      ? close$1()
      : open$1();
    e.stopPropagation();
  }

  function onLinkFilesChange () {
    var isChecked = $(this).prop('checked');
    var $formGroup = $('#linkFilesTemplate').closest('.form-group');
    isChecked
      ? $formGroup.slideDown()
      : $formGroup.slideUp();
    config$4.set('linkFiles', isChecked);
    $('input[name=linkFilesTemplate]').trigger('change');
  }

  function onLinkFilesTemplateChange () {
    var val = $(this).val();
    config$4.set('linkFilesTemplate', val);
    $root$1.trigger('config.debug.updated', 'linkFilesTemplate');
  }

  function onPersistDrawerChange () {
    var isChecked = $(this).is(':checked');
    config$4.set({
      persistDrawer: isChecked,
      openDrawer: isChecked,
      openSidebar: true
    });
  }

  function open$1 () {
    $root$1.find('.debug-options').addClass('show');
    $('body').on('click', onBodyClick);
    $('body').on('keyup', onBodyKeyup);
  }

  function close$1 () {
    $root$1.find('.debug-options').removeClass('show');
    $('body').off('click', onBodyClick);
    $('body').off('keyup', onBodyKeyup);
  }

  var config$5;
  var options;
  var methods; // method filters
  var $root$2;
  var initialized = false;
  var methodLabels = {
    alert: '<i class="fa fa-fw fa-lg fa-bullhorn"></i>Alerts',
    error: '<i class="fa fa-fw fa-lg fa-times-circle"></i>Error',
    warn: '<i class="fa fa-fw fa-lg fa-warning"></i>Warning',
    info: '<i class="fa fa-fw fa-lg fa-info-circle"></i>Info',
    other: '<i class="fa fa-fw fa-lg fa-sticky-note-o"></i>Other'
  };
  var sidebarHtml = '' +
    '<div class="debug-sidebar show no-transition">' +
      '<div class="sidebar-toggle">' +
        '<div class="collapse">' +
          '<i class="fa fa-caret-left"></i>' +
          '<i class="fa fa-ellipsis-v"></i>' +
          '<i class="fa fa-caret-left"></i>' +
        '</div>' +
        '<div class="expand">' +
          '<i class="fa fa-caret-right"></i>' +
          '<i class="fa fa-ellipsis-v"></i>' +
          '<i class="fa fa-caret-right"></i>' +
        '</div>' +
      '</div>' +
      '<div class="sidebar-content">' +
        '<ul class="list-unstyled debug-filters">' +
          '<li class="php-errors">' +
            '<span><i class="fa fa-fw fa-lg fa-code"></i>PHP Errors</span>' +
            '<ul class="list-unstyled">' +
            '</ul>' +
          '</li>' +
          '<li class="channels">' +
            '<span><i class="fa fa-fw fa-lg fa-list-ul"></i>Channels</span>' +
            '<ul class="list-unstyled">' +
            '</ul>' +
          '</li>' +
        '</ul>' +
        '<button class="expand-all" style="display:none;"><i class="fa fa-lg fa-plus"></i> Exp All Groups</button>' +
      '</div>' +
    '</div>';

  function init$6 ($debugRoot) {
    var $debugTabLog = $debugRoot.find('> .tab-panes > .tab-primary');

    config$5 = $debugRoot.data('config') || $('body').data('config');
    $root$2 = $debugRoot;

    if ($debugTabLog.length && $debugTabLog.data('options').sidebar) {
      addMarkup$1($root$2);
    }

    if (config$5.get('persistDrawer') && !config$5.get('openSidebar')) {
      close$2($root$2);
    }

    $root$2.on('click', '.close[data-dismiss=alert]', onClickCloseAlert);
    $root$2.on('click', '.sidebar-toggle', onClickSidebarToggle);
    $root$2.on('change', '.debug-sidebar input[type=checkbox]', onChangeSidebarInput);

    if (initialized) {
      return
    }

    addPreFilter(preFilter);
    addTest(filterTest);
    initialized = true;
  }

  function onChangeSidebarInput (e) {
    var $input = $(this);
    var $toggle = $input.closest('.toggle');
    var $nested = $toggle.next('ul').find('.toggle');
    var isActive = $input.is(':checked');
    var $errorSummary = $('.m_alert.error-summary.have-fatal');
    $toggle.toggleClass('active', isActive);
    $nested.toggleClass('active', isActive);
    if ($input.val() === 'fatal') {
      $errorSummary.find('.error-fatal').toggleClass('filter-hidden', !isActive);
      $errorSummary.toggleClass('filter-hidden', $errorSummary.children().not('.filter-hidden').length === 0);
    }
  }

  function onClickCloseAlert () {
    // setTimeout -> new thread -> executed after event bubbled
    var $debug = $(this).closest('.debug');
    setTimeout(function () {
      if ($debug.find('.m_alert').length) {
        $debug.find('.debug-sidebar input[data-toggle=method][value=alert]').parent().addClass('disabled');
      }
    });
  }

  function onClickSidebarToggle () {
    var $debug = $(this).closest('.debug');
    var isVis = $debug.find('.debug-sidebar').is('.show');
    if (!isVis) {
      open$2($debug);
    } else {
      close$2($debug);
    }
  }

  function filterTest ($node) {
    var matches = $node[0].className.match(/\bm_(\S+)\b/);
    var method = matches ? matches[1] : null;
    if (!options.sidebar) {
      return true
    }
    if (method === 'group' && $node.find('> .group-body')[0].className.match(/level-(error|info|warn)/)) {
      method = $node.find('> .group-body')[0].className.match(/level-(error|info|warn)/)[1];
      $node.toggleClass('filter-hidden-body', methods.indexOf(method) < 0);
    }
    if (['alert', 'error', 'warn', 'info'].indexOf(method) > -1) {
      return methods.indexOf(method) > -1
    }
    return methods.indexOf('other') > -1
  }

  function preFilter ($delegateRoot) {
    $root$2 = $delegateRoot;
    options = $root$2.find('.tab-primary').data('options');
    methods = [];
    $root$2.find('input[data-toggle=method]:checked').each(function () {
      methods.push($(this).val());
    });
  }

  function addMarkup$1 ($node) {
    var $sidebar = $(sidebarHtml);
    var $expAll = $node.find('.tab-panes > .tab-primary > .tab-body > .expand-all');
    $node.find('.tab-panes > .tab-primary > .tab-body').before($sidebar);

    updateErrorSummary($node);
    phpErrorToggles($node);
    moveChannelToggles($node);
    addMethodToggles($node);
    if ($expAll.length) {
      $expAll.remove();
      $sidebar.find('.expand-all').show();
    }
    setTimeout(function () {
      $sidebar.removeClass('no-transition');
    }, 500);
  }

  function close$2 ($node) {
    $node.find('.debug-sidebar')
      .removeClass('show')
      .attr('style', '')
      .trigger('close.debug.sidebar');
    config$5.set('openSidebar', false);
  }

  function open$2 ($node) {
    $node.find('.debug-sidebar')
      .addClass('show')
      .trigger('open.debug.sidebar');
    config$5.set('openSidebar', true);
  }

  /**
   * @param $node debugroot
   */
  function addMethodToggles ($node) {
    var channelNameRoot = $node.data('channelNameRoot');
    var $filters = $node.find('.debug-filters');
    var $entries = $node.find('.tab-primary').find('> .tab-panes .m_alert, .group-body > *');
    var val;
    var haveEntry;
    for (val in methodLabels) {
      haveEntry = val === 'other'
        ? $entries.not('.m_alert, .m_error, .m_warn, .m_info').length > 0
        : $entries.filter('.m_' + val).not('[data-channel="' + channelNameRoot + '.phpError"]').length > 0;
      $filters.append(
        $('<li />').append(
          $('<label class="toggle active" />').toggleClass('disabled', !haveEntry).append(
            $('<input />', {
              type: 'checkbox',
              checked: true,
              'data-toggle': 'method',
              value: val
            })
          ).append(
            $('<span>').append(methodLabels[val])
          )
        )
      );
    }
  }

  /**
   * grab the .tab-panes toggles and move them to sidebar
   */
  function moveChannelToggles ($node) {
    var $togglesSrc = $node.find('.tab-panes .channels > ul > li');
    var $togglesDest = $node.find('.debug-sidebar .channels ul');
    $togglesDest.append($togglesSrc);
    if ($togglesDest.children().length === 0) {
      $togglesDest.parent().hide();
    }
    $node.find('> .tab-panes > .tab-primary > .tab-body > .channels').remove();
  }

  /**
   * Build php error toggles
   */
  function phpErrorToggles ($node) {
    var $togglesUl = $node.find('.debug-sidebar .php-errors ul');
    var categories = ['fatal', 'error', 'warning', 'deprecated', 'notice', 'strict'];
    $.each(categories, function (i, category) {
      var count = category === 'fatal'
        ? $node.find('.m_alert.error-summary.have-fatal').length
        : $node.find('.error-' + category).filter('.m_error,.m_warn').length;
      if (count === 0) {
        return
      }
      $togglesUl.append(
        $('<li>').append(
          $('<label class="toggle active">').html(
            '<input type="checkbox" checked data-toggle="error" data-count="' + count + '" value="' + category + '" />' +
            category + ' <span class="badge">' + count + '</span>'
          )
        )
      );
    });
    if ($togglesUl.children().length === 0) {
      $togglesUl.parent().hide();
    }
  }

  function updateErrorSummary ($node) {
    var $errorSummary = $node.closest('.debug').find('.m_alert.error-summary');
    var $inConsole = $errorSummary.find('.in-console');
    $inConsole.prev().remove();
    $inConsole.remove();
    if ($errorSummary.children().length === 0) {
      $errorSummary.remove();
    }
  }

  /**
   * Add primary Ui elements
   */

  var config$6;
  var $root$3;

  function init$7 ($debugRoot) {
    $root$3 = $debugRoot;
    config$6 = $root$3.data('config').get();
    updateMenuBar();
    addChannelToggles();
    addExpandAll();
    addNoti($('body'));
    enhanceErrorSummary();
    init$3($root$3);
    init$4($root$3);
    init$6($root$3);
    init$5($root$3);
    addErrorIcons();
    $root$3.find('.loading').hide();
    $root$3.addClass('enhanced');
  }

  function updateMenuBar () {
    var $menuBar = $root$3.find('.debug-menu-bar');
    var nav = $menuBar.find('nav').length
      ? $menuBar.find('nav')[0].outerHTML
      : '';
    $menuBar.html('<span><i class="fa fa-bug"></i> PHPDebugConsole</span>' +
      nav +
      '<div class="float-right"></div>'
    );
  }

  function addChannelToggles () {
    var channelNameRoot = $root$3.data('channelNameRoot');
    var $log = $root$3.find('> .tab-panes > .tab-primary');
    var channels = $root$3.data('channels') || {};
    var $ul;
    var $toggles;
    if (!channelNameRoot) {
      return
    }
    if (!channels[channelNameRoot]) {
      return
    }
    $ul = buildChannelList(channels[channelNameRoot].channels, channelNameRoot);
    if ($ul.html().length) {
      $toggles = $('<fieldset />', {
        class: 'channels'
      })
        .append('<legend>Channels</legend>')
        .append($ul);
      $log.find('> .tab-body').prepend($toggles);
    }
  }

  function addErrorIcons () {
    var channelNameRoot = $root$3.data('channelNameRoot');
    var counts = {
      error: $root$3.find('.m_error[data-channel="' + channelNameRoot + '.phpError"]').length,
      warn: $root$3.find('.m_warn[data-channel="' + channelNameRoot + '.phpError"]').length
    };
    var $icon;
    var $icons = $('<span>', { class: 'debug-error-counts' });
    $.each(['error', 'warn'], function (i, what) {
      if (counts[what] === 0) {
        return
      }
      $icon = $(config$6.iconsMethods['.m_' + what]).removeClass('fa-lg').addClass('text-' + what);
      $icons.append($icon).append($('<span>', {
        class: 'badge',
        html: counts[what]
      }));
    });
    $root$3.find('.debug-pull-tab').append($icons[0].outerHTML);
    $root$3.find('.debug-menu-bar .float-right').prepend($icons);
  }

  function addExpandAll () {
    var $expandAll = $('<button>', {
      class: 'expand-all'
    }).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups');
    var $logBody = $root$3.find('> .tab-panes > .tab-primary > .tab-body');

    // this is currently invoked before entries are enhance / empty class not yet added
    if ($logBody.find('.m_group:not(.empty)').length > 1) {
      $logBody.find('.debug-log-summary').before($expandAll);
    }
    $root$3.on('click', '.expand-all', function () {
      $(this).closest('.debug').find('.m_group:not(.expanded)').debugEnhance('expand');
      return false
    });
  }

  function addNoti ($root) {
    if ($root.find('.debug-noti-wrap').length) {
      return
    }
    $root.append('<div class="debug-noti-wrap">' +
        '<div class="debug-noti-table">' +
          '<div class="debug-noti"></div>' +
        '</div>' +
      '</div>');
  }

  function buildChannelList (channels, nameRoot, checkedChannels, prepend) {
    var $li;
    var $lis = [];
    var $ul = $('<ul class="list-unstyled">');
    /*
    console.log('buildChannelList', {
      nameRoot: nameRoot,
      prepend: prepend,
      channels: channels
    })
    */
    prepend = prepend || '';
    if ($.isArray(channels)) {
      channels = channelsToTree(channels);
    } else if (prepend.length === 0 && Object.keys(channels).length) {
      // start with (add) if there are other channels
      // console.log('buildChannelLi name root', nameRoot)
      $li = buildChannelLi(
        nameRoot,
        nameRoot,
        true,
        true,
        {}
      );
      $ul.append($li);
    }
    $lis = buildChannelLis(channels, nameRoot, checkedChannels, prepend);
    for (var i = 0, len = $lis.length; i < len; i++) {
      $ul.append($lis[i]);
    }
    return $ul
  }

  function buildChannelValue (channelName, prepend, nameRoot) {
    var value = channelName;
    if (prepend) {
      value = prepend + channelName;
    } else if (value !== nameRoot) {
      value = nameRoot + '.' + value;
    }
    return value
  }

  function buildChannelLis (channels, nameRoot, checkedChannels, prepend) {
    var $li;
    var $lis = [];
    var channel;
    var channelName = '';
    var channelNames = Object.keys(channels).sort(function (a, b) {
      return a.localeCompare(b)
    });
    var isChecked = true;
    var value;
    for (var i = 0, len = channelNames.length; i < len; i++) {
      channelName = channelNames[i];
      if (channelName === 'phpError') {
        // phpError is a special channel
        continue
      }
      channel = channels[channelName];
      value = buildChannelValue(channelName, prepend, nameRoot);
      isChecked = checkedChannels !== undefined
        ? checkedChannels.indexOf(value) > -1
        : channel.options.show;
      $li = buildChannelLi(
        channelName,
        value,
        isChecked,
        channelName === nameRoot,
        channel.options
      );
      if (Object.keys(channel.channels).length) {
        $li.append(buildChannelList(channel.channels, nameRoot, checkedChannels, value + '.'));
      }
      $lis.push($li);
    }
    return $lis
  }

  function buildChannelLi (channelName, value, isChecked, isRoot, options) {
    var $label;
    var $li;
    $label = $('<label>', {
      class: 'toggle'
    }).append($('<input>', {
      checked: isChecked,
      'data-is-root': isRoot,
      'data-toggle': 'channel',
      type: 'checkbox',
      value: value
    })).append(channelName);
    $label.toggleClass('active', isChecked);
    $li = $('<li>').append($label);
    if (options.icon) {
      $li.find('input').after($('<i>', { class: options.icon }));
    }
    return $li
  }

  function channelsToTree (channels) {
    var channelTree = {};
    var channel;
    var ref;
    var i;
    var path;
    for (i = 0; i < channels.length; i++) {
      ref = channelTree;
      channel = channels[i];
      path = channel.name.split('.');
      if (path.length > 1 && path[0] === channels[0].name) {
        path.shift();
      }
      channelsToTreeWalkPath(channel, path, ref);
    }
    return channelTree
  }

  function channelsToTreeWalkPath (channel, path, channelTreeRef) {
    var i;
    var options;
    for (i = 0; i < path.length; i++) {
      options = i === path.length - 1
        ? {
          icon: channel.icon,
          show: channel.show
        }
        : {
          icon: null,
          show: null
        };
      if (!channelTreeRef[path[i]]) {
        channelTreeRef[path[i]] = {
          options: options,
          channels: {}
        };
      }
      channelTreeRef = channelTreeRef[path[i]].channels;
    }
  }

  /**
   * ErrorSummary should be considered deprecated
   */
  function enhanceErrorSummary () {
    var $errorSummary = $root$3.find('.m_alert.error-summary');
    $errorSummary.find('h3:first-child').prepend(config$6.iconsMethods['.m_error']);
    $errorSummary.find('.in-console li[class*=error-]').each(function () {
      var category = $(this).attr('class').replace('error-', '');
      var html = $(this).html();
      var htmlReplace = '<li><label>' +
        '<input type="checkbox" checked data-toggle="error" data-count="' + $(this).data('count') + '" value="' + category + '" /> ' +
        html +
        '</label></li>';
      $(this).replaceWith(htmlReplace);
    });
    $errorSummary.find('.m_trace').debugEnhance();
  }

  /**
   * handle expanding/collapsing arrays, groups, & objects
   */

  var config$7;

  function init$8 ($delegateNode) {
    config$7 = $delegateNode.data('config').get();
    $delegateNode.on('click', '[data-toggle=array]', onClickToggle);
    $delegateNode.on('click', '[data-toggle=group]', onClickToggle);
    $delegateNode.on('click', '[data-toggle=next]', function (e) {
      if ($(e.target).closest('a,button').length) {
        return
      }
      return onClickToggle.call(this)
    });
    $delegateNode.on('click', '[data-toggle=object]', onClickToggle);
    $delegateNode.on('collapsed.debug.group updated.debug.group', function (e) {
      groupUpdate($(e.target));
    });
    $delegateNode.on('expanded.debug.group', function (e) {
      var $target = $(e.target);
      $target.find('> .group-header > i:last-child').remove();
    });
  }

  /**
   * Collapse an array, group, or object
   *
   * @param jQueryObj $toggle   the toggle node
   * @param immediate immediate no annimation
   *
   * @return void
   */
  function collapse ($node, immediate) {
    var isToggle = $node.is('[data-toggle]');
    var what = isToggle
      ? $node.data('toggle')
      : $node.find('> *[data-toggle]').data('toggle');
    var $wrap = isToggle
      ? $node.parent()
      : $node;
    var $toggle = isToggle
      ? $node
      : $wrap.find('> *[data-toggle]');
    var eventNameDone = 'collapsed.debug.' + what;
    if (what === 'array') {
      $wrap.removeClass('expanded');
    } else if (['group', 'object'].indexOf(what) > -1) {
      collapseGroupObject($wrap, $toggle, immediate, eventNameDone);
    } else if (what === 'next') {
      collapseNext($toggle, immediate, eventNameDone);
    }
  }

  function expand ($node) {
    var icon = config$7.iconsExpand.collapse;
    var isToggle = $node.is('[data-toggle]');
    var what = isToggle
      ? $node.data('toggle')
      : ($node.find('> *[data-toggle]').data('toggle') || ($node.attr('class').match(/\bt_(\w+)/) || []).pop());
    var $wrap = isToggle
      ? $node.parent()
      : $node;
    var $toggle = isToggle
      ? $node
      : $wrap.find('> *[data-toggle]');
    var $classTarget = what === 'next' // node that get's "expanded" class
      ? $toggle
      : $wrap;
    var $evtTarget = what === 'next' // node we trigger events on
      ? $toggle.next()
      : $wrap;
    var eventNameDone = 'expanded.debug.' + what;
    // trigger while still hidden!
    //    no redraws
    $evtTarget.trigger('expand.debug.' + what);
    if (what === 'array') {
      $classTarget.addClass('expanded');
      $evtTarget.trigger(eventNameDone);
      return
    }
    // group, object, & next
    expandGroupObjNext($toggle, $classTarget, $evtTarget, icon, eventNameDone);
  }

  function toggle (node) {
    var $node = $(node);
    var isToggle = $node.is('[data-toggle]');
    var what = isToggle
      ? $node.data('toggle')
      : $node.find('> *[data-toggle]').data('toggle');
    var $wrap = isToggle
      ? $node.parent()
      : $node;
    var isExpanded = what === 'next'
      ? $node.hasClass('expanded')
      : $wrap.hasClass('expanded');
    if (what === 'group' && $wrap.hasClass('.empty')) {
      return
    }
    isExpanded
      ? collapse($node)
      : expand($node);
  }

  function findFirstDefined (list) {
    for (var i = 0, count = list.length; i < count; i++) {
      if (list[i] !== undefined) {
        return list[i]
      }
    }
  }

  function getNodeType ($node) {
    var matches = $node.prop('class').match(/t_(\w+)|(timestamp|string-encoded)/);
    var type;
    var typeMore = $node.data('typeMore');
    if (matches === null) {
      return getNodeTypeNoMatch($node)
    }
    type = findFirstDefined(matches.slice(1)) || 'unknown';
    if (type === 'timestamp') {
      type = $node.find('> span').prop('class').replace('t_', '');
      typeMore = 'timestamp';
    } else if (type === 'string-encoded') {
      type = 'string';
      typeMore = $node.data('typeMore');
    }
    return [type, typeMore]
  }

  function getNodeTypeNoMatch ($node) {
    var type = $node.data('type') || 'unknown';
    var typeMore = $node.data('typeMore');
    if ($node.hasClass('show-more-container')) {
      type = 'string';
    } else if ($node.hasClass('value-container') && $node.find('.content-type').length) {
      typeMore = $node.find('.content-type').text();
    }
    return [type, typeMore]
  }

  /**
   * Build the value displayed when group is collapsed
   */
  function buildReturnVal ($return) {
    var type = getNodeType($return);
    var typeMore = type[1];
    type = type[0];
    if (['bool', 'callable', 'const', 'float', 'int', 'null', 'resource', 'unknown'].indexOf(type) > -1 || ['numeric', 'timestamp'].indexOf(typeMore) > -1) {
      return $return[0].outerHTML
    }
    if (type === 'string') {
      return buildReturnValString($return, typeMore)
    }
    if (type === 'object') {
      return $return.find('> .classname, > [data-toggle] > .classname, > .t_const, > [data-toggle] > .t_const')[0].outerHTML
    }
    if (type === 'array' && $return[0].textContent === 'array()') {
      return $return[0].outerHTML.replace('t_array', 't_array expanded')
    }
    return '<span class="t_keyword">' + type + '</span>'
  }

  function buildReturnValString ($return, typeMore) {
    if (typeMore === 'classname') {
      return $return[0].outerHTML
    }
    return typeMore
      ? '<span><span class="t_keyword">string</span><span class="text-muted">(' + typeMore + ')</span></span>'
      : ($return[0].innerHTML.indexOf('\n') < 0
        ? $return[0].outerHTML
        : '<span class="t_keyword">string</span>')
  }

  /**
   * Collapse group or object
   */
  function collapseGroupObject ($wrap, $toggle, immediate, eventNameDone) {
    var $groupEndValue = $wrap.find('> .group-body > .m_groupEndValue > :last-child');
    if ($groupEndValue.length && $toggle.find('.group-label').last().nextAll().length === 0) {
      $toggle.find('.group-label').last()
        .after('<span class="t_operator"> : </span>' + buildReturnVal($groupEndValue));
    }
    if (immediate) {
      return collapseGroupObjectDone($wrap, $toggle, eventNameDone)
    }
    $toggle.next().slideUp('fast', function () {
      collapseGroupObjectDone($wrap, $toggle, eventNameDone);
    });
  }

  function collapseGroupObjectDone ($wrap, $toggle, eventNameDone) {
    var icon = config$7.iconsExpand.expand;
    $wrap.removeClass('expanded');
    iconUpdate($toggle, icon);
    $wrap.trigger(eventNameDone);
  }

  function collapseNext ($toggle, immediate, eventNameDone) {
    if (immediate) {
      $toggle.next().hide();
      collapseNextDone($toggle, eventNameDone);
      return
    }
    $toggle.next().slideUp('fast', function () {
      collapseNextDone($toggle, eventNameDone);
    });
  }

  function collapseNextDone ($toggle, eventNameDone) {
    var icon = config$7.iconsExpand.expand;
    $toggle.removeClass('expanded');
    iconUpdate($toggle, icon);
    $toggle.next().trigger(eventNameDone);
  }

  function expandGroupObjNext ($toggle, $classTarget, $evtTarget, icon, eventNameDone) {
    $toggle.next().slideDown('fast', function () {
      var $groupEndValue = $(this).find('> .m_groupEndValue');
      if ($groupEndValue.length) {
        // remove value from label
        $toggle.find('.group-label').last().nextAll().remove();
      }
      $classTarget.addClass('expanded');
      iconUpdate($toggle, icon);
      $evtTarget.trigger(eventNameDone);
    });
  }

  function groupErrorIconGet ($group) {
    var icon = '';
    var channel = $group.data('channel');
    var filter = function (i, node) {
      var $node = $(node);
      if ($node.hasClass('filter-hidden')) {
        // only collect hidden errors if of the same channel & channel also hidden
        return $group.hasClass('filter-hidden') && $node.data('channel') === channel
      }
      return true
    };
    if ($group.find('.m_error').filter(filter).length) {
      icon = config$7.iconsMethods['.m_error'];
    } else if ($group.find('.m_warn').filter(filter).length) {
      icon = config$7.iconsMethods['.m_warn'];
    }
    return icon
  }

  /**
   * Does group have any visible children
   *
   * @param $group .m_group jQuery obj
   *
   * @return bool
   */
  function groupHasVis ($group) {
    var $children = $group.find('> .group-body > *');
    var $entry;
    var count;
    var i;
    for (i = 0, count = $children.length; i < count; i++) {
      $entry = $children.eq(i);
      if ($entry.hasClass('filter-hidden')) {
        if ($entry.hasClass('m_group') === false) {
          continue
        }
        if (groupHasVis($entry)) {
          return true
        }
        continue
      }
      if ($entry.is('.m_group.hide-if-empty.empty')) {
        continue
      }
      return true
    }
  }

  /**
   * Update expand/collapse icon and nested error/warn icon
   */
  function groupUpdate ($group) {
    var selector = '> i:last-child';
    var $toggle = $group.find('> .group-header');
    var haveVis = groupHasVis($group);
    var icon = groupErrorIconGet($group);
    var isExpanded = $group.hasClass('expanded');
    // console.log('groupUpdate', $toggle.text(), icon, haveVis)
    $group.toggleClass('empty', !haveVis); // 'empty' class just affects cursor
    iconUpdate($toggle, config$7.iconsExpand[isExpanded ? 'collapse' : 'expand']);
    if (!icon || isExpanded) {
      $toggle.find(selector).remove();
      return
    }
    if ($toggle.find(selector).length) {
      $toggle.find(selector).replaceWith(icon);
      return
    }
    $toggle.append(icon);
  }

  function iconUpdate ($toggle, classNameNew) {
    var $icon = $toggle.children('i').eq(0);
    if ($toggle.hasClass('group-header') && $toggle.parent().hasClass('empty')) {
      classNameNew = config$7.iconsExpand.empty;
    }
    $.each(config$7.iconsExpand, function (i, className) {
      $icon.toggleClass(className, className === classNameNew);
    });
  }

  function onClickToggle () {
    toggle(this);
    return false
  }

  /**
   * handle tabs
   */

  function init$9 ($delegateNode) {
    // config = $delegateNode.data('config').get()
    var $tabPanes = $delegateNode.find('.tab-panes');
    $delegateNode.find('nav .nav-link').each(function (i, tab) {
      initTab($(tab), $tabPanes);
    });
    $delegateNode.on('click', '[data-toggle=tab]', function () {
      show(this);
      return false
    });
    $delegateNode.on('shown.debug.tab', function (e) {
      var $target = $(e.target);
      if ($target.hasClass('string-raw')) {
        $target.debugEnhance();
        return
      }
      $target.find('.m_alert, .group-body:visible').debugEnhance();
    });
  }

  function initTab ($tab, $tabPanes) {
    var targetSelector = $tab.data('target');
    var $tabPane = $tabPanes.find(targetSelector).eq(0);
    if ($tab.hasClass('active')) {
      // don't hide or highlight primary tab
      return // continue
    }
    if ($tabPane.text().trim().length === 0) {
      $tab.hide();
    } else if ($tabPane.find('.m_error').length) {
      $tab.addClass('has-error');
    } else if ($tabPane.find('.m_warn').length) {
      $tab.addClass('has-warn');
    } else if ($tabPane.find('.m_assert').length) {
      $tab.addClass('has-assert');
    }
  }

  function show (node) {
    var $tab = $(node);
    var targetSelector = $tab.data('target');
    // .tabs-container may wrap the nav and the tabs-panes...
    var $context = (function () {
      var $tabsContainer = $tab.closest('.tabs-container');
      return $tabsContainer.length
        ? $tabsContainer
        : $tab.closest('.debug').find('.tab-panes')
    })();
    var $tabPane = $context.find(targetSelector).eq(0);
    $tab.siblings().removeClass('active');
    $tab.addClass('active');
    $tabPane.siblings().removeClass('active');
    $tabPane.addClass('active');
    $tabPane.trigger('shown.debug.tab');
  }

  var top = 'top';
  var bottom = 'bottom';
  var right = 'right';
  var left = 'left';
  var auto = 'auto';
  var basePlacements = [top, bottom, right, left];
  var start = 'start';
  var end = 'end';
  var clippingParents = 'clippingParents';
  var viewport = 'viewport';
  var popper = 'popper';
  var reference = 'reference';
  var variationPlacements = /*#__PURE__*/basePlacements.reduce(function (acc, placement) {
    return acc.concat([placement + "-" + start, placement + "-" + end]);
  }, []);
  var placements = /*#__PURE__*/[].concat(basePlacements, [auto]).reduce(function (acc, placement) {
    return acc.concat([placement, placement + "-" + start, placement + "-" + end]);
  }, []); // modifiers that need to read the DOM

  var beforeRead = 'beforeRead';
  var read = 'read';
  var afterRead = 'afterRead'; // pure-logic modifiers

  var beforeMain = 'beforeMain';
  var main = 'main';
  var afterMain = 'afterMain'; // modifier with the purpose to write to the DOM (or write into a framework state)

  var beforeWrite = 'beforeWrite';
  var write = 'write';
  var afterWrite = 'afterWrite';
  var modifierPhases = [beforeRead, read, afterRead, beforeMain, main, afterMain, beforeWrite, write, afterWrite];

  function getNodeName(element) {
    return element ? (element.nodeName || '').toLowerCase() : null;
  }

  /*:: import type { Window } from '../types'; */

  /*:: declare function getWindow(node: Node | Window): Window; */
  function getWindow(node) {
    if (node.toString() !== '[object Window]') {
      var ownerDocument = node.ownerDocument;
      return ownerDocument ? ownerDocument.defaultView || window : window;
    }

    return node;
  }

  /*:: declare function isElement(node: mixed): boolean %checks(node instanceof
    Element); */

  function isElement(node) {
    var OwnElement = getWindow(node).Element;
    return node instanceof OwnElement || node instanceof Element;
  }
  /*:: declare function isHTMLElement(node: mixed): boolean %checks(node instanceof
    HTMLElement); */


  function isHTMLElement(node) {
    var OwnElement = getWindow(node).HTMLElement;
    return node instanceof OwnElement || node instanceof HTMLElement;
  }
  /*:: declare function isShadowRoot(node: mixed): boolean %checks(node instanceof
    ShadowRoot); */


  function isShadowRoot(node) {
    var OwnElement = getWindow(node).ShadowRoot;
    return node instanceof OwnElement || node instanceof ShadowRoot;
  }

  // and applies them to the HTMLElements such as popper and arrow

  function applyStyles(_ref) {
    var state = _ref.state;
    Object.keys(state.elements).forEach(function (name) {
      var style = state.styles[name] || {};
      var attributes = state.attributes[name] || {};
      var element = state.elements[name]; // arrow is optional + virtual elements

      if (!isHTMLElement(element) || !getNodeName(element)) {
        return;
      } // Flow doesn't support to extend this property, but it's the most
      // effective way to apply styles to an HTMLElement
      // $FlowFixMe


      Object.assign(element.style, style);
      Object.keys(attributes).forEach(function (name) {
        var value = attributes[name];

        if (value === false) {
          element.removeAttribute(name);
        } else {
          element.setAttribute(name, value === true ? '' : value);
        }
      });
    });
  }

  function effect(_ref2) {
    var state = _ref2.state;
    var initialStyles = {
      popper: {
        position: state.options.strategy,
        left: '0',
        top: '0',
        margin: '0'
      },
      arrow: {
        position: 'absolute'
      },
      reference: {}
    };
    Object.assign(state.elements.popper.style, initialStyles.popper);

    if (state.elements.arrow) {
      Object.assign(state.elements.arrow.style, initialStyles.arrow);
    }

    return function () {
      Object.keys(state.elements).forEach(function (name) {
        var element = state.elements[name];
        var attributes = state.attributes[name] || {};
        var styleProperties = Object.keys(state.styles.hasOwnProperty(name) ? state.styles[name] : initialStyles[name]); // Set all values to an empty string to unset them

        var style = styleProperties.reduce(function (style, property) {
          style[property] = '';
          return style;
        }, {}); // arrow is optional + virtual elements

        if (!isHTMLElement(element) || !getNodeName(element)) {
          return;
        } // Flow doesn't support to extend this property, but it's the most
        // effective way to apply styles to an HTMLElement
        // $FlowFixMe


        Object.assign(element.style, style);
        Object.keys(attributes).forEach(function (attribute) {
          element.removeAttribute(attribute);
        });
      });
    };
  } // eslint-disable-next-line import/no-unused-modules


  var applyStyles$1 = {
    name: 'applyStyles',
    enabled: true,
    phase: 'write',
    fn: applyStyles,
    effect: effect,
    requires: ['computeStyles']
  };

  function getBasePlacement(placement) {
    return placement.split('-')[0];
  }

  // Returns the layout rect of an element relative to its offsetParent. Layout
  // means it doesn't take into account transforms.
  function getLayoutRect(element) {
    return {
      x: element.offsetLeft,
      y: element.offsetTop,
      width: element.offsetWidth,
      height: element.offsetHeight
    };
  }

  function contains(parent, child) {
    var rootNode = child.getRootNode && child.getRootNode(); // First, attempt with faster native method

    if (parent.contains(child)) {
      return true;
    } // then fallback to custom implementation with Shadow DOM support
    else if (rootNode && isShadowRoot(rootNode)) {
        var next = child;

        do {
          if (next && parent.isSameNode(next)) {
            return true;
          } // $FlowFixMe: need a better way to handle this...


          next = next.parentNode || next.host;
        } while (next);
      } // Give up, the result is false


    return false;
  }

  function getComputedStyle(element) {
    return getWindow(element).getComputedStyle(element);
  }

  function isTableElement(element) {
    return ['table', 'td', 'th'].indexOf(getNodeName(element)) >= 0;
  }

  function getDocumentElement(element) {
    // $FlowFixMe: assume body is always available
    return ((isElement(element) ? element.ownerDocument : element.document) || window.document).documentElement;
  }

  function getParentNode(element) {
    if (getNodeName(element) === 'html') {
      return element;
    }

    return (// $FlowFixMe: this is a quicker (but less type safe) way to save quite some bytes from the bundle
      element.assignedSlot || // step into the shadow DOM of the parent of a slotted node
      element.parentNode || // DOM Element detected
      // $FlowFixMe: need a better way to handle this...
      element.host || // ShadowRoot detected
      // $FlowFixMe: HTMLElement is a Node
      getDocumentElement(element) // fallback

    );
  }

  function getTrueOffsetParent(element) {
    if (!isHTMLElement(element) || // https://github.com/popperjs/popper-core/issues/837
    getComputedStyle(element).position === 'fixed') {
      return null;
    }

    var offsetParent = element.offsetParent;

    if (offsetParent) {
      var html = getDocumentElement(offsetParent);

      if (getNodeName(offsetParent) === 'body' && getComputedStyle(offsetParent).position === 'static' && getComputedStyle(html).position !== 'static') {
        return html;
      }
    }

    return offsetParent;
  } // `.offsetParent` reports `null` for fixed elements, while absolute elements
  // return the containing block


  function getContainingBlock(element) {
    var currentNode = getParentNode(element);

    while (isHTMLElement(currentNode) && ['html', 'body'].indexOf(getNodeName(currentNode)) < 0) {
      var css = getComputedStyle(currentNode); // This is non-exhaustive but covers the most common CSS properties that
      // create a containing block.

      if (css.transform !== 'none' || css.perspective !== 'none' || css.willChange && css.willChange !== 'auto') {
        return currentNode;
      } else {
        currentNode = currentNode.parentNode;
      }
    }

    return null;
  } // Gets the closest ancestor positioned element. Handles some edge cases,
  // such as table ancestors and cross browser bugs.


  function getOffsetParent(element) {
    var window = getWindow(element);
    var offsetParent = getTrueOffsetParent(element);

    while (offsetParent && isTableElement(offsetParent) && getComputedStyle(offsetParent).position === 'static') {
      offsetParent = getTrueOffsetParent(offsetParent);
    }

    if (offsetParent && getNodeName(offsetParent) === 'body' && getComputedStyle(offsetParent).position === 'static') {
      return window;
    }

    return offsetParent || getContainingBlock(element) || window;
  }

  function getMainAxisFromPlacement(placement) {
    return ['top', 'bottom'].indexOf(placement) >= 0 ? 'x' : 'y';
  }

  function within(min, value, max) {
    return Math.max(min, Math.min(value, max));
  }

  function getFreshSideObject() {
    return {
      top: 0,
      right: 0,
      bottom: 0,
      left: 0
    };
  }

  function mergePaddingObject(paddingObject) {
    return Object.assign(Object.assign({}, getFreshSideObject()), paddingObject);
  }

  function expandToHashMap(value, keys) {
    return keys.reduce(function (hashMap, key) {
      hashMap[key] = value;
      return hashMap;
    }, {});
  }

  function arrow(_ref) {
    var _state$modifiersData$;

    var state = _ref.state,
        name = _ref.name;
    var arrowElement = state.elements.arrow;
    var popperOffsets = state.modifiersData.popperOffsets;
    var basePlacement = getBasePlacement(state.placement);
    var axis = getMainAxisFromPlacement(basePlacement);
    var isVertical = [left, right].indexOf(basePlacement) >= 0;
    var len = isVertical ? 'height' : 'width';

    if (!arrowElement || !popperOffsets) {
      return;
    }

    var paddingObject = state.modifiersData[name + "#persistent"].padding;
    var arrowRect = getLayoutRect(arrowElement);
    var minProp = axis === 'y' ? top : left;
    var maxProp = axis === 'y' ? bottom : right;
    var endDiff = state.rects.reference[len] + state.rects.reference[axis] - popperOffsets[axis] - state.rects.popper[len];
    var startDiff = popperOffsets[axis] - state.rects.reference[axis];
    var arrowOffsetParent = getOffsetParent(arrowElement);
    var clientSize = arrowOffsetParent ? axis === 'y' ? arrowOffsetParent.clientHeight || 0 : arrowOffsetParent.clientWidth || 0 : 0;
    var centerToReference = endDiff / 2 - startDiff / 2; // Make sure the arrow doesn't overflow the popper if the center point is
    // outside of the popper bounds

    var min = paddingObject[minProp];
    var max = clientSize - arrowRect[len] - paddingObject[maxProp];
    var center = clientSize / 2 - arrowRect[len] / 2 + centerToReference;
    var offset = within(min, center, max); // Prevents breaking syntax highlighting...

    var axisProp = axis;
    state.modifiersData[name] = (_state$modifiersData$ = {}, _state$modifiersData$[axisProp] = offset, _state$modifiersData$.centerOffset = offset - center, _state$modifiersData$);
  }

  function effect$1(_ref2) {
    var state = _ref2.state,
        options = _ref2.options,
        name = _ref2.name;
    var _options$element = options.element,
        arrowElement = _options$element === void 0 ? '[data-popper-arrow]' : _options$element,
        _options$padding = options.padding,
        padding = _options$padding === void 0 ? 0 : _options$padding;

    if (arrowElement == null) {
      return;
    } // CSS selector


    if (typeof arrowElement === 'string') {
      arrowElement = state.elements.popper.querySelector(arrowElement);

      if (!arrowElement) {
        return;
      }
    }

    {
      if (!isHTMLElement(arrowElement)) {
        console.error(['Popper: "arrow" element must be an HTMLElement (not an SVGElement).', 'To use an SVG arrow, wrap it in an HTMLElement that will be used as', 'the arrow.'].join(' '));
      }
    }

    if (!contains(state.elements.popper, arrowElement)) {
      {
        console.error(['Popper: "arrow" modifier\'s `element` must be a child of the popper', 'element.'].join(' '));
      }

      return;
    }

    state.elements.arrow = arrowElement;
    state.modifiersData[name + "#persistent"] = {
      padding: mergePaddingObject(typeof padding !== 'number' ? padding : expandToHashMap(padding, basePlacements))
    };
  } // eslint-disable-next-line import/no-unused-modules


  var arrow$1 = {
    name: 'arrow',
    enabled: true,
    phase: 'main',
    fn: arrow,
    effect: effect$1,
    requires: ['popperOffsets'],
    requiresIfExists: ['preventOverflow']
  };

  var unsetSides = {
    top: 'auto',
    right: 'auto',
    bottom: 'auto',
    left: 'auto'
  }; // Round the offsets to the nearest suitable subpixel based on the DPR.
  // Zooming can change the DPR, but it seems to report a value that will
  // cleanly divide the values into the appropriate subpixels.

  function roundOffsets(_ref) {
    var x = _ref.x,
        y = _ref.y;
    var win = window;
    var dpr = win.devicePixelRatio || 1;
    return {
      x: Math.round(x * dpr) / dpr || 0,
      y: Math.round(y * dpr) / dpr || 0
    };
  }

  function mapToStyles(_ref2) {
    var _Object$assign2;

    var popper = _ref2.popper,
        popperRect = _ref2.popperRect,
        placement = _ref2.placement,
        offsets = _ref2.offsets,
        position = _ref2.position,
        gpuAcceleration = _ref2.gpuAcceleration,
        adaptive = _ref2.adaptive;

    var _roundOffsets = roundOffsets(offsets),
        x = _roundOffsets.x,
        y = _roundOffsets.y;

    var hasX = offsets.hasOwnProperty('x');
    var hasY = offsets.hasOwnProperty('y');
    var sideX = left;
    var sideY = top;
    var win = window;

    if (adaptive) {
      var offsetParent = getOffsetParent(popper);

      if (offsetParent === getWindow(popper)) {
        offsetParent = getDocumentElement(popper);
      } // $FlowFixMe: force type refinement, we compare offsetParent with window above, but Flow doesn't detect it

      /*:: offsetParent = (offsetParent: Element); */


      if (placement === top) {
        sideY = bottom;
        y -= offsetParent.clientHeight - popperRect.height;
        y *= gpuAcceleration ? 1 : -1;
      }

      if (placement === left) {
        sideX = right;
        x -= offsetParent.clientWidth - popperRect.width;
        x *= gpuAcceleration ? 1 : -1;
      }
    }

    var commonStyles = Object.assign({
      position: position
    }, adaptive && unsetSides);

    if (gpuAcceleration) {
      var _Object$assign;

      return Object.assign(Object.assign({}, commonStyles), {}, (_Object$assign = {}, _Object$assign[sideY] = hasY ? '0' : '', _Object$assign[sideX] = hasX ? '0' : '', _Object$assign.transform = (win.devicePixelRatio || 1) < 2 ? "translate(" + x + "px, " + y + "px)" : "translate3d(" + x + "px, " + y + "px, 0)", _Object$assign));
    }

    return Object.assign(Object.assign({}, commonStyles), {}, (_Object$assign2 = {}, _Object$assign2[sideY] = hasY ? y + "px" : '', _Object$assign2[sideX] = hasX ? x + "px" : '', _Object$assign2.transform = '', _Object$assign2));
  }

  function computeStyles(_ref3) {
    var state = _ref3.state,
        options = _ref3.options;
    var _options$gpuAccelerat = options.gpuAcceleration,
        gpuAcceleration = _options$gpuAccelerat === void 0 ? true : _options$gpuAccelerat,
        _options$adaptive = options.adaptive,
        adaptive = _options$adaptive === void 0 ? true : _options$adaptive;

    {
      var transitionProperty = getComputedStyle(state.elements.popper).transitionProperty || '';

      if (adaptive && ['transform', 'top', 'right', 'bottom', 'left'].some(function (property) {
        return transitionProperty.indexOf(property) >= 0;
      })) {
        console.warn(['Popper: Detected CSS transitions on at least one of the following', 'CSS properties: "transform", "top", "right", "bottom", "left".', '\n\n', 'Disable the "computeStyles" modifier\'s `adaptive` option to allow', 'for smooth transitions, or remove these properties from the CSS', 'transition declaration on the popper element if only transitioning', 'opacity or background-color for example.', '\n\n', 'We recommend using the popper element as a wrapper around an inner', 'element that can have any CSS property transitioned for animations.'].join(' '));
      }
    }

    var commonStyles = {
      placement: getBasePlacement(state.placement),
      popper: state.elements.popper,
      popperRect: state.rects.popper,
      gpuAcceleration: gpuAcceleration
    };

    if (state.modifiersData.popperOffsets != null) {
      state.styles.popper = Object.assign(Object.assign({}, state.styles.popper), mapToStyles(Object.assign(Object.assign({}, commonStyles), {}, {
        offsets: state.modifiersData.popperOffsets,
        position: state.options.strategy,
        adaptive: adaptive
      })));
    }

    if (state.modifiersData.arrow != null) {
      state.styles.arrow = Object.assign(Object.assign({}, state.styles.arrow), mapToStyles(Object.assign(Object.assign({}, commonStyles), {}, {
        offsets: state.modifiersData.arrow,
        position: 'absolute',
        adaptive: false
      })));
    }

    state.attributes.popper = Object.assign(Object.assign({}, state.attributes.popper), {}, {
      'data-popper-placement': state.placement
    });
  } // eslint-disable-next-line import/no-unused-modules


  var computeStyles$1 = {
    name: 'computeStyles',
    enabled: true,
    phase: 'beforeWrite',
    fn: computeStyles,
    data: {}
  };

  var passive = {
    passive: true
  };

  function effect$2(_ref) {
    var state = _ref.state,
        instance = _ref.instance,
        options = _ref.options;
    var _options$scroll = options.scroll,
        scroll = _options$scroll === void 0 ? true : _options$scroll,
        _options$resize = options.resize,
        resize = _options$resize === void 0 ? true : _options$resize;
    var window = getWindow(state.elements.popper);
    var scrollParents = [].concat(state.scrollParents.reference, state.scrollParents.popper);

    if (scroll) {
      scrollParents.forEach(function (scrollParent) {
        scrollParent.addEventListener('scroll', instance.update, passive);
      });
    }

    if (resize) {
      window.addEventListener('resize', instance.update, passive);
    }

    return function () {
      if (scroll) {
        scrollParents.forEach(function (scrollParent) {
          scrollParent.removeEventListener('scroll', instance.update, passive);
        });
      }

      if (resize) {
        window.removeEventListener('resize', instance.update, passive);
      }
    };
  } // eslint-disable-next-line import/no-unused-modules


  var eventListeners = {
    name: 'eventListeners',
    enabled: true,
    phase: 'write',
    fn: function fn() {},
    effect: effect$2,
    data: {}
  };

  var hash = {
    left: 'right',
    right: 'left',
    bottom: 'top',
    top: 'bottom'
  };
  function getOppositePlacement(placement) {
    return placement.replace(/left|right|bottom|top/g, function (matched) {
      return hash[matched];
    });
  }

  var hash$1 = {
    start: 'end',
    end: 'start'
  };
  function getOppositeVariationPlacement(placement) {
    return placement.replace(/start|end/g, function (matched) {
      return hash$1[matched];
    });
  }

  function getBoundingClientRect(element) {
    var rect = element.getBoundingClientRect();
    return {
      width: rect.width,
      height: rect.height,
      top: rect.top,
      right: rect.right,
      bottom: rect.bottom,
      left: rect.left,
      x: rect.left,
      y: rect.top
    };
  }

  function getWindowScroll(node) {
    var win = getWindow(node);
    var scrollLeft = win.pageXOffset;
    var scrollTop = win.pageYOffset;
    return {
      scrollLeft: scrollLeft,
      scrollTop: scrollTop
    };
  }

  function getWindowScrollBarX(element) {
    // If <html> has a CSS width greater than the viewport, then this will be
    // incorrect for RTL.
    // Popper 1 is broken in this case and never had a bug report so let's assume
    // it's not an issue. I don't think anyone ever specifies width on <html>
    // anyway.
    // Browsers where the left scrollbar doesn't cause an issue report `0` for
    // this (e.g. Edge 2019, IE11, Safari)
    return getBoundingClientRect(getDocumentElement(element)).left + getWindowScroll(element).scrollLeft;
  }

  function getViewportRect(element) {
    var win = getWindow(element);
    var html = getDocumentElement(element);
    var visualViewport = win.visualViewport;
    var width = html.clientWidth;
    var height = html.clientHeight;
    var x = 0;
    var y = 0; // NB: This isn't supported on iOS <= 12. If the keyboard is open, the popper
    // can be obscured underneath it.
    // Also, `html.clientHeight` adds the bottom bar height in Safari iOS, even
    // if it isn't open, so if this isn't available, the popper will be detected
    // to overflow the bottom of the screen too early.

    if (visualViewport) {
      width = visualViewport.width;
      height = visualViewport.height; // Uses Layout Viewport (like Chrome; Safari does not currently)
      // In Chrome, it returns a value very close to 0 (+/-) but contains rounding
      // errors due to floating point numbers, so we need to check precision.
      // Safari returns a number <= 0, usually < -1 when pinch-zoomed
      // Feature detection fails in mobile emulation mode in Chrome.
      // Math.abs(win.innerWidth / visualViewport.scale - visualViewport.width) <
      // 0.001
      // Fallback here: "Not Safari" userAgent

      if (!/^((?!chrome|android).)*safari/i.test(navigator.userAgent)) {
        x = visualViewport.offsetLeft;
        y = visualViewport.offsetTop;
      }
    }

    return {
      width: width,
      height: height,
      x: x + getWindowScrollBarX(element),
      y: y
    };
  }

  // of the `<html>` and `<body>` rect bounds if horizontally scrollable

  function getDocumentRect(element) {
    var html = getDocumentElement(element);
    var winScroll = getWindowScroll(element);
    var body = element.ownerDocument.body;
    var width = Math.max(html.scrollWidth, html.clientWidth, body ? body.scrollWidth : 0, body ? body.clientWidth : 0);
    var height = Math.max(html.scrollHeight, html.clientHeight, body ? body.scrollHeight : 0, body ? body.clientHeight : 0);
    var x = -winScroll.scrollLeft + getWindowScrollBarX(element);
    var y = -winScroll.scrollTop;

    if (getComputedStyle(body || html).direction === 'rtl') {
      x += Math.max(html.clientWidth, body ? body.clientWidth : 0) - width;
    }

    return {
      width: width,
      height: height,
      x: x,
      y: y
    };
  }

  function isScrollParent(element) {
    // Firefox wants us to check `-x` and `-y` variations as well
    var _getComputedStyle = getComputedStyle(element),
        overflow = _getComputedStyle.overflow,
        overflowX = _getComputedStyle.overflowX,
        overflowY = _getComputedStyle.overflowY;

    return /auto|scroll|overlay|hidden/.test(overflow + overflowY + overflowX);
  }

  function getScrollParent(node) {
    if (['html', 'body', '#document'].indexOf(getNodeName(node)) >= 0) {
      // $FlowFixMe: assume body is always available
      return node.ownerDocument.body;
    }

    if (isHTMLElement(node) && isScrollParent(node)) {
      return node;
    }

    return getScrollParent(getParentNode(node));
  }

  /*
  given a DOM element, return the list of all scroll parents, up the list of ancesors
  until we get to the top window object. This list is what we attach scroll listeners
  to, because if any of these parent elements scroll, we'll need to re-calculate the 
  reference element's position.
  */

  function listScrollParents(element, list) {
    if (list === void 0) {
      list = [];
    }

    var scrollParent = getScrollParent(element);
    var isBody = getNodeName(scrollParent) === 'body';
    var win = getWindow(scrollParent);
    var target = isBody ? [win].concat(win.visualViewport || [], isScrollParent(scrollParent) ? scrollParent : []) : scrollParent;
    var updatedList = list.concat(target);
    return isBody ? updatedList : // $FlowFixMe: isBody tells us target will be an HTMLElement here
    updatedList.concat(listScrollParents(getParentNode(target)));
  }

  function rectToClientRect(rect) {
    return Object.assign(Object.assign({}, rect), {}, {
      left: rect.x,
      top: rect.y,
      right: rect.x + rect.width,
      bottom: rect.y + rect.height
    });
  }

  function getInnerBoundingClientRect(element) {
    var rect = getBoundingClientRect(element);
    rect.top = rect.top + element.clientTop;
    rect.left = rect.left + element.clientLeft;
    rect.bottom = rect.top + element.clientHeight;
    rect.right = rect.left + element.clientWidth;
    rect.width = element.clientWidth;
    rect.height = element.clientHeight;
    rect.x = rect.left;
    rect.y = rect.top;
    return rect;
  }

  function getClientRectFromMixedType(element, clippingParent) {
    return clippingParent === viewport ? rectToClientRect(getViewportRect(element)) : isHTMLElement(clippingParent) ? getInnerBoundingClientRect(clippingParent) : rectToClientRect(getDocumentRect(getDocumentElement(element)));
  } // A "clipping parent" is an overflowable container with the characteristic of
  // clipping (or hiding) overflowing elements with a position different from
  // `initial`


  function getClippingParents(element) {
    var clippingParents = listScrollParents(getParentNode(element));
    var canEscapeClipping = ['absolute', 'fixed'].indexOf(getComputedStyle(element).position) >= 0;
    var clipperElement = canEscapeClipping && isHTMLElement(element) ? getOffsetParent(element) : element;

    if (!isElement(clipperElement)) {
      return [];
    } // $FlowFixMe: https://github.com/facebook/flow/issues/1414


    return clippingParents.filter(function (clippingParent) {
      return isElement(clippingParent) && contains(clippingParent, clipperElement) && getNodeName(clippingParent) !== 'body';
    });
  } // Gets the maximum area that the element is visible in due to any number of
  // clipping parents


  function getClippingRect(element, boundary, rootBoundary) {
    var mainClippingParents = boundary === 'clippingParents' ? getClippingParents(element) : [].concat(boundary);
    var clippingParents = [].concat(mainClippingParents, [rootBoundary]);
    var firstClippingParent = clippingParents[0];
    var clippingRect = clippingParents.reduce(function (accRect, clippingParent) {
      var rect = getClientRectFromMixedType(element, clippingParent);
      accRect.top = Math.max(rect.top, accRect.top);
      accRect.right = Math.min(rect.right, accRect.right);
      accRect.bottom = Math.min(rect.bottom, accRect.bottom);
      accRect.left = Math.max(rect.left, accRect.left);
      return accRect;
    }, getClientRectFromMixedType(element, firstClippingParent));
    clippingRect.width = clippingRect.right - clippingRect.left;
    clippingRect.height = clippingRect.bottom - clippingRect.top;
    clippingRect.x = clippingRect.left;
    clippingRect.y = clippingRect.top;
    return clippingRect;
  }

  function getVariation(placement) {
    return placement.split('-')[1];
  }

  function computeOffsets(_ref) {
    var reference = _ref.reference,
        element = _ref.element,
        placement = _ref.placement;
    var basePlacement = placement ? getBasePlacement(placement) : null;
    var variation = placement ? getVariation(placement) : null;
    var commonX = reference.x + reference.width / 2 - element.width / 2;
    var commonY = reference.y + reference.height / 2 - element.height / 2;
    var offsets;

    switch (basePlacement) {
      case top:
        offsets = {
          x: commonX,
          y: reference.y - element.height
        };
        break;

      case bottom:
        offsets = {
          x: commonX,
          y: reference.y + reference.height
        };
        break;

      case right:
        offsets = {
          x: reference.x + reference.width,
          y: commonY
        };
        break;

      case left:
        offsets = {
          x: reference.x - element.width,
          y: commonY
        };
        break;

      default:
        offsets = {
          x: reference.x,
          y: reference.y
        };
    }

    var mainAxis = basePlacement ? getMainAxisFromPlacement(basePlacement) : null;

    if (mainAxis != null) {
      var len = mainAxis === 'y' ? 'height' : 'width';

      switch (variation) {
        case start:
          offsets[mainAxis] = Math.floor(offsets[mainAxis]) - Math.floor(reference[len] / 2 - element[len] / 2);
          break;

        case end:
          offsets[mainAxis] = Math.floor(offsets[mainAxis]) + Math.ceil(reference[len] / 2 - element[len] / 2);
          break;
      }
    }

    return offsets;
  }

  function detectOverflow(state, options) {
    if (options === void 0) {
      options = {};
    }

    var _options = options,
        _options$placement = _options.placement,
        placement = _options$placement === void 0 ? state.placement : _options$placement,
        _options$boundary = _options.boundary,
        boundary = _options$boundary === void 0 ? clippingParents : _options$boundary,
        _options$rootBoundary = _options.rootBoundary,
        rootBoundary = _options$rootBoundary === void 0 ? viewport : _options$rootBoundary,
        _options$elementConte = _options.elementContext,
        elementContext = _options$elementConte === void 0 ? popper : _options$elementConte,
        _options$altBoundary = _options.altBoundary,
        altBoundary = _options$altBoundary === void 0 ? false : _options$altBoundary,
        _options$padding = _options.padding,
        padding = _options$padding === void 0 ? 0 : _options$padding;
    var paddingObject = mergePaddingObject(typeof padding !== 'number' ? padding : expandToHashMap(padding, basePlacements));
    var altContext = elementContext === popper ? reference : popper;
    var referenceElement = state.elements.reference;
    var popperRect = state.rects.popper;
    var element = state.elements[altBoundary ? altContext : elementContext];
    var clippingClientRect = getClippingRect(isElement(element) ? element : element.contextElement || getDocumentElement(state.elements.popper), boundary, rootBoundary);
    var referenceClientRect = getBoundingClientRect(referenceElement);
    var popperOffsets = computeOffsets({
      reference: referenceClientRect,
      element: popperRect,
      strategy: 'absolute',
      placement: placement
    });
    var popperClientRect = rectToClientRect(Object.assign(Object.assign({}, popperRect), popperOffsets));
    var elementClientRect = elementContext === popper ? popperClientRect : referenceClientRect; // positive = overflowing the clipping rect
    // 0 or negative = within the clipping rect

    var overflowOffsets = {
      top: clippingClientRect.top - elementClientRect.top + paddingObject.top,
      bottom: elementClientRect.bottom - clippingClientRect.bottom + paddingObject.bottom,
      left: clippingClientRect.left - elementClientRect.left + paddingObject.left,
      right: elementClientRect.right - clippingClientRect.right + paddingObject.right
    };
    var offsetData = state.modifiersData.offset; // Offsets can be applied only to the popper element

    if (elementContext === popper && offsetData) {
      var offset = offsetData[placement];
      Object.keys(overflowOffsets).forEach(function (key) {
        var multiply = [right, bottom].indexOf(key) >= 0 ? 1 : -1;
        var axis = [top, bottom].indexOf(key) >= 0 ? 'y' : 'x';
        overflowOffsets[key] += offset[axis] * multiply;
      });
    }

    return overflowOffsets;
  }

  /*:: type OverflowsMap = { [ComputedPlacement]: number }; */

  /*;; type OverflowsMap = { [key in ComputedPlacement]: number }; */
  function computeAutoPlacement(state, options) {
    if (options === void 0) {
      options = {};
    }

    var _options = options,
        placement = _options.placement,
        boundary = _options.boundary,
        rootBoundary = _options.rootBoundary,
        padding = _options.padding,
        flipVariations = _options.flipVariations,
        _options$allowedAutoP = _options.allowedAutoPlacements,
        allowedAutoPlacements = _options$allowedAutoP === void 0 ? placements : _options$allowedAutoP;
    var variation = getVariation(placement);
    var placements$1 = variation ? flipVariations ? variationPlacements : variationPlacements.filter(function (placement) {
      return getVariation(placement) === variation;
    }) : basePlacements; // $FlowFixMe

    var allowedPlacements = placements$1.filter(function (placement) {
      return allowedAutoPlacements.indexOf(placement) >= 0;
    });

    if (allowedPlacements.length === 0) {
      allowedPlacements = placements$1;

      {
        console.error(['Popper: The `allowedAutoPlacements` option did not allow any', 'placements. Ensure the `placement` option matches the variation', 'of the allowed placements.', 'For example, "auto" cannot be used to allow "bottom-start".', 'Use "auto-start" instead.'].join(' '));
      }
    } // $FlowFixMe: Flow seems to have problems with two array unions...


    var overflows = allowedPlacements.reduce(function (acc, placement) {
      acc[placement] = detectOverflow(state, {
        placement: placement,
        boundary: boundary,
        rootBoundary: rootBoundary,
        padding: padding
      })[getBasePlacement(placement)];
      return acc;
    }, {});
    return Object.keys(overflows).sort(function (a, b) {
      return overflows[a] - overflows[b];
    });
  }

  function getExpandedFallbackPlacements(placement) {
    if (getBasePlacement(placement) === auto) {
      return [];
    }

    var oppositePlacement = getOppositePlacement(placement);
    return [getOppositeVariationPlacement(placement), oppositePlacement, getOppositeVariationPlacement(oppositePlacement)];
  }

  function flip(_ref) {
    var state = _ref.state,
        options = _ref.options,
        name = _ref.name;

    if (state.modifiersData[name]._skip) {
      return;
    }

    var _options$mainAxis = options.mainAxis,
        checkMainAxis = _options$mainAxis === void 0 ? true : _options$mainAxis,
        _options$altAxis = options.altAxis,
        checkAltAxis = _options$altAxis === void 0 ? true : _options$altAxis,
        specifiedFallbackPlacements = options.fallbackPlacements,
        padding = options.padding,
        boundary = options.boundary,
        rootBoundary = options.rootBoundary,
        altBoundary = options.altBoundary,
        _options$flipVariatio = options.flipVariations,
        flipVariations = _options$flipVariatio === void 0 ? true : _options$flipVariatio,
        allowedAutoPlacements = options.allowedAutoPlacements;
    var preferredPlacement = state.options.placement;
    var basePlacement = getBasePlacement(preferredPlacement);
    var isBasePlacement = basePlacement === preferredPlacement;
    var fallbackPlacements = specifiedFallbackPlacements || (isBasePlacement || !flipVariations ? [getOppositePlacement(preferredPlacement)] : getExpandedFallbackPlacements(preferredPlacement));
    var placements = [preferredPlacement].concat(fallbackPlacements).reduce(function (acc, placement) {
      return acc.concat(getBasePlacement(placement) === auto ? computeAutoPlacement(state, {
        placement: placement,
        boundary: boundary,
        rootBoundary: rootBoundary,
        padding: padding,
        flipVariations: flipVariations,
        allowedAutoPlacements: allowedAutoPlacements
      }) : placement);
    }, []);
    var referenceRect = state.rects.reference;
    var popperRect = state.rects.popper;
    var checksMap = new Map();
    var makeFallbackChecks = true;
    var firstFittingPlacement = placements[0];

    for (var i = 0; i < placements.length; i++) {
      var placement = placements[i];

      var _basePlacement = getBasePlacement(placement);

      var isStartVariation = getVariation(placement) === start;
      var isVertical = [top, bottom].indexOf(_basePlacement) >= 0;
      var len = isVertical ? 'width' : 'height';
      var overflow = detectOverflow(state, {
        placement: placement,
        boundary: boundary,
        rootBoundary: rootBoundary,
        altBoundary: altBoundary,
        padding: padding
      });
      var mainVariationSide = isVertical ? isStartVariation ? right : left : isStartVariation ? bottom : top;

      if (referenceRect[len] > popperRect[len]) {
        mainVariationSide = getOppositePlacement(mainVariationSide);
      }

      var altVariationSide = getOppositePlacement(mainVariationSide);
      var checks = [];

      if (checkMainAxis) {
        checks.push(overflow[_basePlacement] <= 0);
      }

      if (checkAltAxis) {
        checks.push(overflow[mainVariationSide] <= 0, overflow[altVariationSide] <= 0);
      }

      if (checks.every(function (check) {
        return check;
      })) {
        firstFittingPlacement = placement;
        makeFallbackChecks = false;
        break;
      }

      checksMap.set(placement, checks);
    }

    if (makeFallbackChecks) {
      // `2` may be desired in some cases – research later
      var numberOfChecks = flipVariations ? 3 : 1;

      var _loop = function _loop(_i) {
        var fittingPlacement = placements.find(function (placement) {
          var checks = checksMap.get(placement);

          if (checks) {
            return checks.slice(0, _i).every(function (check) {
              return check;
            });
          }
        });

        if (fittingPlacement) {
          firstFittingPlacement = fittingPlacement;
          return "break";
        }
      };

      for (var _i = numberOfChecks; _i > 0; _i--) {
        var _ret = _loop(_i);

        if (_ret === "break") break;
      }
    }

    if (state.placement !== firstFittingPlacement) {
      state.modifiersData[name]._skip = true;
      state.placement = firstFittingPlacement;
      state.reset = true;
    }
  } // eslint-disable-next-line import/no-unused-modules


  var flip$1 = {
    name: 'flip',
    enabled: true,
    phase: 'main',
    fn: flip,
    requiresIfExists: ['offset'],
    data: {
      _skip: false
    }
  };

  function getSideOffsets(overflow, rect, preventedOffsets) {
    if (preventedOffsets === void 0) {
      preventedOffsets = {
        x: 0,
        y: 0
      };
    }

    return {
      top: overflow.top - rect.height - preventedOffsets.y,
      right: overflow.right - rect.width + preventedOffsets.x,
      bottom: overflow.bottom - rect.height + preventedOffsets.y,
      left: overflow.left - rect.width - preventedOffsets.x
    };
  }

  function isAnySideFullyClipped(overflow) {
    return [top, right, bottom, left].some(function (side) {
      return overflow[side] >= 0;
    });
  }

  function hide(_ref) {
    var state = _ref.state,
        name = _ref.name;
    var referenceRect = state.rects.reference;
    var popperRect = state.rects.popper;
    var preventedOffsets = state.modifiersData.preventOverflow;
    var referenceOverflow = detectOverflow(state, {
      elementContext: 'reference'
    });
    var popperAltOverflow = detectOverflow(state, {
      altBoundary: true
    });
    var referenceClippingOffsets = getSideOffsets(referenceOverflow, referenceRect);
    var popperEscapeOffsets = getSideOffsets(popperAltOverflow, popperRect, preventedOffsets);
    var isReferenceHidden = isAnySideFullyClipped(referenceClippingOffsets);
    var hasPopperEscaped = isAnySideFullyClipped(popperEscapeOffsets);
    state.modifiersData[name] = {
      referenceClippingOffsets: referenceClippingOffsets,
      popperEscapeOffsets: popperEscapeOffsets,
      isReferenceHidden: isReferenceHidden,
      hasPopperEscaped: hasPopperEscaped
    };
    state.attributes.popper = Object.assign(Object.assign({}, state.attributes.popper), {}, {
      'data-popper-reference-hidden': isReferenceHidden,
      'data-popper-escaped': hasPopperEscaped
    });
  } // eslint-disable-next-line import/no-unused-modules


  var hide$1 = {
    name: 'hide',
    enabled: true,
    phase: 'main',
    requiresIfExists: ['preventOverflow'],
    fn: hide
  };

  function distanceAndSkiddingToXY(placement, rects, offset) {
    var basePlacement = getBasePlacement(placement);
    var invertDistance = [left, top].indexOf(basePlacement) >= 0 ? -1 : 1;

    var _ref = typeof offset === 'function' ? offset(Object.assign(Object.assign({}, rects), {}, {
      placement: placement
    })) : offset,
        skidding = _ref[0],
        distance = _ref[1];

    skidding = skidding || 0;
    distance = (distance || 0) * invertDistance;
    return [left, right].indexOf(basePlacement) >= 0 ? {
      x: distance,
      y: skidding
    } : {
      x: skidding,
      y: distance
    };
  }

  function offset(_ref2) {
    var state = _ref2.state,
        options = _ref2.options,
        name = _ref2.name;
    var _options$offset = options.offset,
        offset = _options$offset === void 0 ? [0, 0] : _options$offset;
    var data = placements.reduce(function (acc, placement) {
      acc[placement] = distanceAndSkiddingToXY(placement, state.rects, offset);
      return acc;
    }, {});
    var _data$state$placement = data[state.placement],
        x = _data$state$placement.x,
        y = _data$state$placement.y;

    if (state.modifiersData.popperOffsets != null) {
      state.modifiersData.popperOffsets.x += x;
      state.modifiersData.popperOffsets.y += y;
    }

    state.modifiersData[name] = data;
  } // eslint-disable-next-line import/no-unused-modules


  var offset$1 = {
    name: 'offset',
    enabled: true,
    phase: 'main',
    requires: ['popperOffsets'],
    fn: offset
  };

  function popperOffsets(_ref) {
    var state = _ref.state,
        name = _ref.name;
    // Offsets are the actual position the popper needs to have to be
    // properly positioned near its reference element
    // This is the most basic placement, and will be adjusted by
    // the modifiers in the next step
    state.modifiersData[name] = computeOffsets({
      reference: state.rects.reference,
      element: state.rects.popper,
      strategy: 'absolute',
      placement: state.placement
    });
  } // eslint-disable-next-line import/no-unused-modules


  var popperOffsets$1 = {
    name: 'popperOffsets',
    enabled: true,
    phase: 'read',
    fn: popperOffsets,
    data: {}
  };

  function getAltAxis(axis) {
    return axis === 'x' ? 'y' : 'x';
  }

  function preventOverflow(_ref) {
    var state = _ref.state,
        options = _ref.options,
        name = _ref.name;
    var _options$mainAxis = options.mainAxis,
        checkMainAxis = _options$mainAxis === void 0 ? true : _options$mainAxis,
        _options$altAxis = options.altAxis,
        checkAltAxis = _options$altAxis === void 0 ? false : _options$altAxis,
        boundary = options.boundary,
        rootBoundary = options.rootBoundary,
        altBoundary = options.altBoundary,
        padding = options.padding,
        _options$tether = options.tether,
        tether = _options$tether === void 0 ? true : _options$tether,
        _options$tetherOffset = options.tetherOffset,
        tetherOffset = _options$tetherOffset === void 0 ? 0 : _options$tetherOffset;
    var overflow = detectOverflow(state, {
      boundary: boundary,
      rootBoundary: rootBoundary,
      padding: padding,
      altBoundary: altBoundary
    });
    var basePlacement = getBasePlacement(state.placement);
    var variation = getVariation(state.placement);
    var isBasePlacement = !variation;
    var mainAxis = getMainAxisFromPlacement(basePlacement);
    var altAxis = getAltAxis(mainAxis);
    var popperOffsets = state.modifiersData.popperOffsets;
    var referenceRect = state.rects.reference;
    var popperRect = state.rects.popper;
    var tetherOffsetValue = typeof tetherOffset === 'function' ? tetherOffset(Object.assign(Object.assign({}, state.rects), {}, {
      placement: state.placement
    })) : tetherOffset;
    var data = {
      x: 0,
      y: 0
    };

    if (!popperOffsets) {
      return;
    }

    if (checkMainAxis) {
      var mainSide = mainAxis === 'y' ? top : left;
      var altSide = mainAxis === 'y' ? bottom : right;
      var len = mainAxis === 'y' ? 'height' : 'width';
      var offset = popperOffsets[mainAxis];
      var min = popperOffsets[mainAxis] + overflow[mainSide];
      var max = popperOffsets[mainAxis] - overflow[altSide];
      var additive = tether ? -popperRect[len] / 2 : 0;
      var minLen = variation === start ? referenceRect[len] : popperRect[len];
      var maxLen = variation === start ? -popperRect[len] : -referenceRect[len]; // We need to include the arrow in the calculation so the arrow doesn't go
      // outside the reference bounds

      var arrowElement = state.elements.arrow;
      var arrowRect = tether && arrowElement ? getLayoutRect(arrowElement) : {
        width: 0,
        height: 0
      };
      var arrowPaddingObject = state.modifiersData['arrow#persistent'] ? state.modifiersData['arrow#persistent'].padding : getFreshSideObject();
      var arrowPaddingMin = arrowPaddingObject[mainSide];
      var arrowPaddingMax = arrowPaddingObject[altSide]; // If the reference length is smaller than the arrow length, we don't want
      // to include its full size in the calculation. If the reference is small
      // and near the edge of a boundary, the popper can overflow even if the
      // reference is not overflowing as well (e.g. virtual elements with no
      // width or height)

      var arrowLen = within(0, referenceRect[len], arrowRect[len]);
      var minOffset = isBasePlacement ? referenceRect[len] / 2 - additive - arrowLen - arrowPaddingMin - tetherOffsetValue : minLen - arrowLen - arrowPaddingMin - tetherOffsetValue;
      var maxOffset = isBasePlacement ? -referenceRect[len] / 2 + additive + arrowLen + arrowPaddingMax + tetherOffsetValue : maxLen + arrowLen + arrowPaddingMax + tetherOffsetValue;
      var arrowOffsetParent = state.elements.arrow && getOffsetParent(state.elements.arrow);
      var clientOffset = arrowOffsetParent ? mainAxis === 'y' ? arrowOffsetParent.clientTop || 0 : arrowOffsetParent.clientLeft || 0 : 0;
      var offsetModifierValue = state.modifiersData.offset ? state.modifiersData.offset[state.placement][mainAxis] : 0;
      var tetherMin = popperOffsets[mainAxis] + minOffset - offsetModifierValue - clientOffset;
      var tetherMax = popperOffsets[mainAxis] + maxOffset - offsetModifierValue;
      var preventedOffset = within(tether ? Math.min(min, tetherMin) : min, offset, tether ? Math.max(max, tetherMax) : max);
      popperOffsets[mainAxis] = preventedOffset;
      data[mainAxis] = preventedOffset - offset;
    }

    if (checkAltAxis) {
      var _mainSide = mainAxis === 'x' ? top : left;

      var _altSide = mainAxis === 'x' ? bottom : right;

      var _offset = popperOffsets[altAxis];

      var _min = _offset + overflow[_mainSide];

      var _max = _offset - overflow[_altSide];

      var _preventedOffset = within(_min, _offset, _max);

      popperOffsets[altAxis] = _preventedOffset;
      data[altAxis] = _preventedOffset - _offset;
    }

    state.modifiersData[name] = data;
  } // eslint-disable-next-line import/no-unused-modules


  var preventOverflow$1 = {
    name: 'preventOverflow',
    enabled: true,
    phase: 'main',
    fn: preventOverflow,
    requiresIfExists: ['offset']
  };

  function getHTMLElementScroll(element) {
    return {
      scrollLeft: element.scrollLeft,
      scrollTop: element.scrollTop
    };
  }

  function getNodeScroll(node) {
    if (node === getWindow(node) || !isHTMLElement(node)) {
      return getWindowScroll(node);
    } else {
      return getHTMLElementScroll(node);
    }
  }

  // Composite means it takes into account transforms as well as layout.

  function getCompositeRect(elementOrVirtualElement, offsetParent, isFixed) {
    if (isFixed === void 0) {
      isFixed = false;
    }

    var documentElement = getDocumentElement(offsetParent);
    var rect = getBoundingClientRect(elementOrVirtualElement);
    var isOffsetParentAnElement = isHTMLElement(offsetParent);
    var scroll = {
      scrollLeft: 0,
      scrollTop: 0
    };
    var offsets = {
      x: 0,
      y: 0
    };

    if (isOffsetParentAnElement || !isOffsetParentAnElement && !isFixed) {
      if (getNodeName(offsetParent) !== 'body' || // https://github.com/popperjs/popper-core/issues/1078
      isScrollParent(documentElement)) {
        scroll = getNodeScroll(offsetParent);
      }

      if (isHTMLElement(offsetParent)) {
        offsets = getBoundingClientRect(offsetParent);
        offsets.x += offsetParent.clientLeft;
        offsets.y += offsetParent.clientTop;
      } else if (documentElement) {
        offsets.x = getWindowScrollBarX(documentElement);
      }
    }

    return {
      x: rect.left + scroll.scrollLeft - offsets.x,
      y: rect.top + scroll.scrollTop - offsets.y,
      width: rect.width,
      height: rect.height
    };
  }

  function order(modifiers) {
    var map = new Map();
    var visited = new Set();
    var result = [];
    modifiers.forEach(function (modifier) {
      map.set(modifier.name, modifier);
    }); // On visiting object, check for its dependencies and visit them recursively

    function sort(modifier) {
      visited.add(modifier.name);
      var requires = [].concat(modifier.requires || [], modifier.requiresIfExists || []);
      requires.forEach(function (dep) {
        if (!visited.has(dep)) {
          var depModifier = map.get(dep);

          if (depModifier) {
            sort(depModifier);
          }
        }
      });
      result.push(modifier);
    }

    modifiers.forEach(function (modifier) {
      if (!visited.has(modifier.name)) {
        // check for visited object
        sort(modifier);
      }
    });
    return result;
  }

  function orderModifiers(modifiers) {
    // order based on dependencies
    var orderedModifiers = order(modifiers); // order based on phase

    return modifierPhases.reduce(function (acc, phase) {
      return acc.concat(orderedModifiers.filter(function (modifier) {
        return modifier.phase === phase;
      }));
    }, []);
  }

  function debounce(fn) {
    var pending;
    return function () {
      if (!pending) {
        pending = new Promise(function (resolve) {
          Promise.resolve().then(function () {
            pending = undefined;
            resolve(fn());
          });
        });
      }

      return pending;
    };
  }

  function format(str) {
    for (var _len = arguments.length, args = new Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
      args[_key - 1] = arguments[_key];
    }

    return [].concat(args).reduce(function (p, c) {
      return p.replace(/%s/, c);
    }, str);
  }

  var INVALID_MODIFIER_ERROR = 'Popper: modifier "%s" provided an invalid %s property, expected %s but got %s';
  var MISSING_DEPENDENCY_ERROR = 'Popper: modifier "%s" requires "%s", but "%s" modifier is not available';
  var VALID_PROPERTIES = ['name', 'enabled', 'phase', 'fn', 'effect', 'requires', 'options'];
  function validateModifiers(modifiers) {
    modifiers.forEach(function (modifier) {
      Object.keys(modifier).forEach(function (key) {
        switch (key) {
          case 'name':
            if (typeof modifier.name !== 'string') {
              console.error(format(INVALID_MODIFIER_ERROR, String(modifier.name), '"name"', '"string"', "\"" + String(modifier.name) + "\""));
            }

            break;

          case 'enabled':
            if (typeof modifier.enabled !== 'boolean') {
              console.error(format(INVALID_MODIFIER_ERROR, modifier.name, '"enabled"', '"boolean"', "\"" + String(modifier.enabled) + "\""));
            }

          case 'phase':
            if (modifierPhases.indexOf(modifier.phase) < 0) {
              console.error(format(INVALID_MODIFIER_ERROR, modifier.name, '"phase"', "either " + modifierPhases.join(', '), "\"" + String(modifier.phase) + "\""));
            }

            break;

          case 'fn':
            if (typeof modifier.fn !== 'function') {
              console.error(format(INVALID_MODIFIER_ERROR, modifier.name, '"fn"', '"function"', "\"" + String(modifier.fn) + "\""));
            }

            break;

          case 'effect':
            if (typeof modifier.effect !== 'function') {
              console.error(format(INVALID_MODIFIER_ERROR, modifier.name, '"effect"', '"function"', "\"" + String(modifier.fn) + "\""));
            }

            break;

          case 'requires':
            if (!Array.isArray(modifier.requires)) {
              console.error(format(INVALID_MODIFIER_ERROR, modifier.name, '"requires"', '"array"', "\"" + String(modifier.requires) + "\""));
            }

            break;

          case 'requiresIfExists':
            if (!Array.isArray(modifier.requiresIfExists)) {
              console.error(format(INVALID_MODIFIER_ERROR, modifier.name, '"requiresIfExists"', '"array"', "\"" + String(modifier.requiresIfExists) + "\""));
            }

            break;

          case 'options':
          case 'data':
            break;

          default:
            console.error("PopperJS: an invalid property has been provided to the \"" + modifier.name + "\" modifier, valid properties are " + VALID_PROPERTIES.map(function (s) {
              return "\"" + s + "\"";
            }).join(', ') + "; but \"" + key + "\" was provided.");
        }

        modifier.requires && modifier.requires.forEach(function (requirement) {
          if (modifiers.find(function (mod) {
            return mod.name === requirement;
          }) == null) {
            console.error(format(MISSING_DEPENDENCY_ERROR, String(modifier.name), requirement, requirement));
          }
        });
      });
    });
  }

  function uniqueBy(arr, fn) {
    var identifiers = new Set();
    return arr.filter(function (item) {
      var identifier = fn(item);

      if (!identifiers.has(identifier)) {
        identifiers.add(identifier);
        return true;
      }
    });
  }

  function mergeByName(modifiers) {
    var merged = modifiers.reduce(function (merged, current) {
      var existing = merged[current.name];
      merged[current.name] = existing ? Object.assign(Object.assign(Object.assign({}, existing), current), {}, {
        options: Object.assign(Object.assign({}, existing.options), current.options),
        data: Object.assign(Object.assign({}, existing.data), current.data)
      }) : current;
      return merged;
    }, {}); // IE11 does not support Object.values

    return Object.keys(merged).map(function (key) {
      return merged[key];
    });
  }

  var INVALID_ELEMENT_ERROR = 'Popper: Invalid reference or popper argument provided. They must be either a DOM element or virtual element.';
  var INFINITE_LOOP_ERROR = 'Popper: An infinite loop in the modifiers cycle has been detected! The cycle has been interrupted to prevent a browser crash.';
  var DEFAULT_OPTIONS = {
    placement: 'bottom',
    modifiers: [],
    strategy: 'absolute'
  };

  function areValidElements() {
    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    return !args.some(function (element) {
      return !(element && typeof element.getBoundingClientRect === 'function');
    });
  }

  function popperGenerator(generatorOptions) {
    if (generatorOptions === void 0) {
      generatorOptions = {};
    }

    var _generatorOptions = generatorOptions,
        _generatorOptions$def = _generatorOptions.defaultModifiers,
        defaultModifiers = _generatorOptions$def === void 0 ? [] : _generatorOptions$def,
        _generatorOptions$def2 = _generatorOptions.defaultOptions,
        defaultOptions = _generatorOptions$def2 === void 0 ? DEFAULT_OPTIONS : _generatorOptions$def2;
    return function createPopper(reference, popper, options) {
      if (options === void 0) {
        options = defaultOptions;
      }

      var state = {
        placement: 'bottom',
        orderedModifiers: [],
        options: Object.assign(Object.assign({}, DEFAULT_OPTIONS), defaultOptions),
        modifiersData: {},
        elements: {
          reference: reference,
          popper: popper
        },
        attributes: {},
        styles: {}
      };
      var effectCleanupFns = [];
      var isDestroyed = false;
      var instance = {
        state: state,
        setOptions: function setOptions(options) {
          cleanupModifierEffects();
          state.options = Object.assign(Object.assign(Object.assign({}, defaultOptions), state.options), options);
          state.scrollParents = {
            reference: isElement(reference) ? listScrollParents(reference) : reference.contextElement ? listScrollParents(reference.contextElement) : [],
            popper: listScrollParents(popper)
          }; // Orders the modifiers based on their dependencies and `phase`
          // properties

          var orderedModifiers = orderModifiers(mergeByName([].concat(defaultModifiers, state.options.modifiers))); // Strip out disabled modifiers

          state.orderedModifiers = orderedModifiers.filter(function (m) {
            return m.enabled;
          }); // Validate the provided modifiers so that the consumer will get warned
          // if one of the modifiers is invalid for any reason

          {
            var modifiers = uniqueBy([].concat(orderedModifiers, state.options.modifiers), function (_ref) {
              var name = _ref.name;
              return name;
            });
            validateModifiers(modifiers);

            if (getBasePlacement(state.options.placement) === auto) {
              var flipModifier = state.orderedModifiers.find(function (_ref2) {
                var name = _ref2.name;
                return name === 'flip';
              });

              if (!flipModifier) {
                console.error(['Popper: "auto" placements require the "flip" modifier be', 'present and enabled to work.'].join(' '));
              }
            }

            var _getComputedStyle = getComputedStyle(popper),
                marginTop = _getComputedStyle.marginTop,
                marginRight = _getComputedStyle.marginRight,
                marginBottom = _getComputedStyle.marginBottom,
                marginLeft = _getComputedStyle.marginLeft; // We no longer take into account `margins` on the popper, and it can
            // cause bugs with positioning, so we'll warn the consumer


            if ([marginTop, marginRight, marginBottom, marginLeft].some(function (margin) {
              return parseFloat(margin);
            })) {
              console.warn(['Popper: CSS "margin" styles cannot be used to apply padding', 'between the popper and its reference element or boundary.', 'To replicate margin, use the `offset` modifier, as well as', 'the `padding` option in the `preventOverflow` and `flip`', 'modifiers.'].join(' '));
            }
          }

          runModifierEffects();
          return instance.update();
        },
        // Sync update – it will always be executed, even if not necessary. This
        // is useful for low frequency updates where sync behavior simplifies the
        // logic.
        // For high frequency updates (e.g. `resize` and `scroll` events), always
        // prefer the async Popper#update method
        forceUpdate: function forceUpdate() {
          if (isDestroyed) {
            return;
          }

          var _state$elements = state.elements,
              reference = _state$elements.reference,
              popper = _state$elements.popper; // Don't proceed if `reference` or `popper` are not valid elements
          // anymore

          if (!areValidElements(reference, popper)) {
            {
              console.error(INVALID_ELEMENT_ERROR);
            }

            return;
          } // Store the reference and popper rects to be read by modifiers


          state.rects = {
            reference: getCompositeRect(reference, getOffsetParent(popper), state.options.strategy === 'fixed'),
            popper: getLayoutRect(popper)
          }; // Modifiers have the ability to reset the current update cycle. The
          // most common use case for this is the `flip` modifier changing the
          // placement, which then needs to re-run all the modifiers, because the
          // logic was previously ran for the previous placement and is therefore
          // stale/incorrect

          state.reset = false;
          state.placement = state.options.placement; // On each update cycle, the `modifiersData` property for each modifier
          // is filled with the initial data specified by the modifier. This means
          // it doesn't persist and is fresh on each update.
          // To ensure persistent data, use `${name}#persistent`

          state.orderedModifiers.forEach(function (modifier) {
            return state.modifiersData[modifier.name] = Object.assign({}, modifier.data);
          });
          var __debug_loops__ = 0;

          for (var index = 0; index < state.orderedModifiers.length; index++) {
            {
              __debug_loops__ += 1;

              if (__debug_loops__ > 100) {
                console.error(INFINITE_LOOP_ERROR);
                break;
              }
            }

            if (state.reset === true) {
              state.reset = false;
              index = -1;
              continue;
            }

            var _state$orderedModifie = state.orderedModifiers[index],
                fn = _state$orderedModifie.fn,
                _state$orderedModifie2 = _state$orderedModifie.options,
                _options = _state$orderedModifie2 === void 0 ? {} : _state$orderedModifie2,
                name = _state$orderedModifie.name;

            if (typeof fn === 'function') {
              state = fn({
                state: state,
                options: _options,
                name: name,
                instance: instance
              }) || state;
            }
          }
        },
        // Async and optimistically optimized update – it will not be executed if
        // not necessary (debounced to run at most once-per-tick)
        update: debounce(function () {
          return new Promise(function (resolve) {
            instance.forceUpdate();
            resolve(state);
          });
        }),
        destroy: function destroy() {
          cleanupModifierEffects();
          isDestroyed = true;
        }
      };

      if (!areValidElements(reference, popper)) {
        {
          console.error(INVALID_ELEMENT_ERROR);
        }

        return instance;
      }

      instance.setOptions(options).then(function (state) {
        if (!isDestroyed && options.onFirstUpdate) {
          options.onFirstUpdate(state);
        }
      }); // Modifiers have the ability to execute arbitrary code before the first
      // update cycle runs. They will be executed in the same order as the update
      // cycle. This is useful when a modifier adds some persistent data that
      // other modifiers need to use, but the modifier is run after the dependent
      // one.

      function runModifierEffects() {
        state.orderedModifiers.forEach(function (_ref3) {
          var name = _ref3.name,
              _ref3$options = _ref3.options,
              options = _ref3$options === void 0 ? {} : _ref3$options,
              effect = _ref3.effect;

          if (typeof effect === 'function') {
            var cleanupFn = effect({
              state: state,
              name: name,
              instance: instance,
              options: options
            });

            var noopFn = function noopFn() {};

            effectCleanupFns.push(cleanupFn || noopFn);
          }
        });
      }

      function cleanupModifierEffects() {
        effectCleanupFns.forEach(function (fn) {
          return fn();
        });
        effectCleanupFns = [];
      }

      return instance;
    };
  }

  var defaultModifiers = [eventListeners, popperOffsets$1, computeStyles$1, applyStyles$1, offset$1, flip$1, preventOverflow$1, arrow$1, hide$1];
  var createPopper = /*#__PURE__*/popperGenerator({
    defaultModifiers: defaultModifiers
  }); // eslint-disable-next-line import/no-unused-modules

  /**!
  * tippy.js v6.2.7
  * (c) 2017-2020 atomiks
  * MIT License
  */
  var BOX_CLASS = "tippy-box";
  var CONTENT_CLASS = "tippy-content";
  var BACKDROP_CLASS = "tippy-backdrop";
  var ARROW_CLASS = "tippy-arrow";
  var SVG_ARROW_CLASS = "tippy-svg-arrow";
  var TOUCH_OPTIONS = {
    passive: true,
    capture: true
  };

  function hasOwnProperty(obj, key) {
    return {}.hasOwnProperty.call(obj, key);
  }
  function getValueAtIndexOrReturn(value, index, defaultValue) {
    if (Array.isArray(value)) {
      var v = value[index];
      return v == null ? Array.isArray(defaultValue) ? defaultValue[index] : defaultValue : v;
    }

    return value;
  }
  function isType(value, type) {
    var str = {}.toString.call(value);
    return str.indexOf('[object') === 0 && str.indexOf(type + "]") > -1;
  }
  function invokeWithArgsOrReturn(value, args) {
    return typeof value === 'function' ? value.apply(void 0, args) : value;
  }
  function debounce$1(fn, ms) {
    // Avoid wrapping in `setTimeout` if ms is 0 anyway
    if (ms === 0) {
      return fn;
    }

    var timeout;
    return function (arg) {
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        fn(arg);
      }, ms);
    };
  }
  function removeProperties(obj, keys) {
    var clone = Object.assign({}, obj);
    keys.forEach(function (key) {
      delete clone[key];
    });
    return clone;
  }
  function splitBySpaces(value) {
    return value.split(/\s+/).filter(Boolean);
  }
  function normalizeToArray(value) {
    return [].concat(value);
  }
  function pushIfUnique(arr, value) {
    if (arr.indexOf(value) === -1) {
      arr.push(value);
    }
  }
  function unique(arr) {
    return arr.filter(function (item, index) {
      return arr.indexOf(item) === index;
    });
  }
  function getBasePlacement$1(placement) {
    return placement.split('-')[0];
  }
  function arrayFrom(value) {
    return [].slice.call(value);
  }
  function removeUndefinedProps(obj) {
    return Object.keys(obj).reduce(function (acc, key) {
      if (obj[key] !== undefined) {
        acc[key] = obj[key];
      }

      return acc;
    }, {});
  }

  function div() {
    return document.createElement('div');
  }
  function isElement$1(value) {
    return ['Element', 'Fragment'].some(function (type) {
      return isType(value, type);
    });
  }
  function isNodeList(value) {
    return isType(value, 'NodeList');
  }
  function isMouseEvent(value) {
    return isType(value, 'MouseEvent');
  }
  function isReferenceElement(value) {
    return !!(value && value._tippy && value._tippy.reference === value);
  }
  function getArrayOfElements(value) {
    if (isElement$1(value)) {
      return [value];
    }

    if (isNodeList(value)) {
      return arrayFrom(value);
    }

    if (Array.isArray(value)) {
      return value;
    }

    return arrayFrom(document.querySelectorAll(value));
  }
  function setTransitionDuration(els, value) {
    els.forEach(function (el) {
      if (el) {
        el.style.transitionDuration = value + "ms";
      }
    });
  }
  function setVisibilityState(els, state) {
    els.forEach(function (el) {
      if (el) {
        el.setAttribute('data-state', state);
      }
    });
  }
  function getOwnerDocument(elementOrElements) {
    var _normalizeToArray = normalizeToArray(elementOrElements),
        element = _normalizeToArray[0];

    return element ? element.ownerDocument || document : document;
  }
  function isCursorOutsideInteractiveBorder(popperTreeData, event) {
    var clientX = event.clientX,
        clientY = event.clientY;
    return popperTreeData.every(function (_ref) {
      var popperRect = _ref.popperRect,
          popperState = _ref.popperState,
          props = _ref.props;
      var interactiveBorder = props.interactiveBorder;
      var basePlacement = getBasePlacement$1(popperState.placement);
      var offsetData = popperState.modifiersData.offset;

      if (!offsetData) {
        return true;
      }

      var topDistance = basePlacement === 'bottom' ? offsetData.top.y : 0;
      var bottomDistance = basePlacement === 'top' ? offsetData.bottom.y : 0;
      var leftDistance = basePlacement === 'right' ? offsetData.left.x : 0;
      var rightDistance = basePlacement === 'left' ? offsetData.right.x : 0;
      var exceedsTop = popperRect.top - clientY + topDistance > interactiveBorder;
      var exceedsBottom = clientY - popperRect.bottom - bottomDistance > interactiveBorder;
      var exceedsLeft = popperRect.left - clientX + leftDistance > interactiveBorder;
      var exceedsRight = clientX - popperRect.right - rightDistance > interactiveBorder;
      return exceedsTop || exceedsBottom || exceedsLeft || exceedsRight;
    });
  }
  function updateTransitionEndListener(box, action, listener) {
    var method = action + "EventListener"; // some browsers apparently support `transition` (unprefixed) but only fire
    // `webkitTransitionEnd`...

    ['transitionend', 'webkitTransitionEnd'].forEach(function (event) {
      box[method](event, listener);
    });
  }

  var currentInput = {
    isTouch: false
  };
  var lastMouseMoveTime = 0;
  /**
   * When a `touchstart` event is fired, it's assumed the user is using touch
   * input. We'll bind a `mousemove` event listener to listen for mouse input in
   * the future. This way, the `isTouch` property is fully dynamic and will handle
   * hybrid devices that use a mix of touch + mouse input.
   */

  function onDocumentTouchStart() {
    if (currentInput.isTouch) {
      return;
    }

    currentInput.isTouch = true;

    if (window.performance) {
      document.addEventListener('mousemove', onDocumentMouseMove);
    }
  }
  /**
   * When two `mousemove` event are fired consecutively within 20ms, it's assumed
   * the user is using mouse input again. `mousemove` can fire on touch devices as
   * well, but very rarely that quickly.
   */

  function onDocumentMouseMove() {
    var now = performance.now();

    if (now - lastMouseMoveTime < 20) {
      currentInput.isTouch = false;
      document.removeEventListener('mousemove', onDocumentMouseMove);
    }

    lastMouseMoveTime = now;
  }
  /**
   * When an element is in focus and has a tippy, leaving the tab/window and
   * returning causes it to show again. For mouse users this is unexpected, but
   * for keyboard use it makes sense.
   * TODO: find a better technique to solve this problem
   */

  function onWindowBlur() {
    var activeElement = document.activeElement;

    if (isReferenceElement(activeElement)) {
      var instance = activeElement._tippy;

      if (activeElement.blur && !instance.state.isVisible) {
        activeElement.blur();
      }
    }
  }
  function bindGlobalEventListeners() {
    document.addEventListener('touchstart', onDocumentTouchStart, TOUCH_OPTIONS);
    window.addEventListener('blur', onWindowBlur);
  }

  var isBrowser = typeof window !== 'undefined' && typeof document !== 'undefined';
  var ua = isBrowser ? navigator.userAgent : '';
  var isIE = /MSIE |Trident\//.test(ua);

  function createMemoryLeakWarning(method) {
    var txt = method === 'destroy' ? 'n already-' : ' ';
    return [method + "() was called on a" + txt + "destroyed instance. This is a no-op but", 'indicates a potential memory leak.'].join(' ');
  }
  function clean(value) {
    var spacesAndTabs = /[ \t]{2,}/g;
    var lineStartWithSpaces = /^[ \t]*/gm;
    return value.replace(spacesAndTabs, ' ').replace(lineStartWithSpaces, '').trim();
  }

  function getDevMessage(message) {
    return clean("\n  %ctippy.js\n\n  %c" + clean(message) + "\n\n  %c\uD83D\uDC77\u200D This is a development-only message. It will be removed in production.\n  ");
  }

  function getFormattedMessage(message) {
    return [getDevMessage(message), // title
    'color: #00C584; font-size: 1.3em; font-weight: bold;', // message
    'line-height: 1.5', // footer
    'color: #a6a095;'];
  } // Assume warnings and errors never have the same message

  var visitedMessages;

  {
    resetVisitedMessages();
  }

  function resetVisitedMessages() {
    visitedMessages = new Set();
  }
  function warnWhen(condition, message) {
    if (condition && !visitedMessages.has(message)) {
      var _console;

      visitedMessages.add(message);

      (_console = console).warn.apply(_console, getFormattedMessage(message));
    }
  }
  function errorWhen(condition, message) {
    if (condition && !visitedMessages.has(message)) {
      var _console2;

      visitedMessages.add(message);

      (_console2 = console).error.apply(_console2, getFormattedMessage(message));
    }
  }
  function validateTargets(targets) {
    var didPassFalsyValue = !targets;
    var didPassPlainObject = Object.prototype.toString.call(targets) === '[object Object]' && !targets.addEventListener;
    errorWhen(didPassFalsyValue, ['tippy() was passed', '`' + String(targets) + '`', 'as its targets (first) argument. Valid types are: String, Element,', 'Element[], or NodeList.'].join(' '));
    errorWhen(didPassPlainObject, ['tippy() was passed a plain object which is not supported as an argument', 'for virtual positioning. Use props.getReferenceClientRect instead.'].join(' '));
  }

  var pluginProps = {
    animateFill: false,
    followCursor: false,
    inlinePositioning: false,
    sticky: false
  };
  var renderProps = {
    allowHTML: false,
    animation: 'fade',
    arrow: true,
    content: '',
    inertia: false,
    maxWidth: 350,
    role: 'tooltip',
    theme: '',
    zIndex: 9999
  };
  var defaultProps = Object.assign({
    appendTo: function appendTo() {
      return document.body;
    },
    aria: {
      content: 'auto',
      expanded: 'auto'
    },
    delay: 0,
    duration: [300, 250],
    getReferenceClientRect: null,
    hideOnClick: true,
    ignoreAttributes: false,
    interactive: false,
    interactiveBorder: 2,
    interactiveDebounce: 0,
    moveTransition: '',
    offset: [0, 10],
    onAfterUpdate: function onAfterUpdate() {},
    onBeforeUpdate: function onBeforeUpdate() {},
    onCreate: function onCreate() {},
    onDestroy: function onDestroy() {},
    onHidden: function onHidden() {},
    onHide: function onHide() {},
    onMount: function onMount() {},
    onShow: function onShow() {},
    onShown: function onShown() {},
    onTrigger: function onTrigger() {},
    onUntrigger: function onUntrigger() {},
    onClickOutside: function onClickOutside() {},
    placement: 'top',
    plugins: [],
    popperOptions: {},
    render: null,
    showOnCreate: false,
    touch: true,
    trigger: 'mouseenter focus',
    triggerTarget: null
  }, pluginProps, {}, renderProps);
  var defaultKeys = Object.keys(defaultProps);
  var setDefaultProps = function setDefaultProps(partialProps) {
    /* istanbul ignore else */
    {
      validateProps(partialProps, []);
    }

    var keys = Object.keys(partialProps);
    keys.forEach(function (key) {
      defaultProps[key] = partialProps[key];
    });
  };
  function getExtendedPassedProps(passedProps) {
    var plugins = passedProps.plugins || [];
    var pluginProps = plugins.reduce(function (acc, plugin) {
      var name = plugin.name,
          defaultValue = plugin.defaultValue;

      if (name) {
        acc[name] = passedProps[name] !== undefined ? passedProps[name] : defaultValue;
      }

      return acc;
    }, {});
    return Object.assign({}, passedProps, {}, pluginProps);
  }
  function getDataAttributeProps(reference, plugins) {
    var propKeys = plugins ? Object.keys(getExtendedPassedProps(Object.assign({}, defaultProps, {
      plugins: plugins
    }))) : defaultKeys;
    var props = propKeys.reduce(function (acc, key) {
      var valueAsString = (reference.getAttribute("data-tippy-" + key) || '').trim();

      if (!valueAsString) {
        return acc;
      }

      if (key === 'content') {
        acc[key] = valueAsString;
      } else {
        try {
          acc[key] = JSON.parse(valueAsString);
        } catch (e) {
          acc[key] = valueAsString;
        }
      }

      return acc;
    }, {});
    return props;
  }
  function evaluateProps(reference, props) {
    var out = Object.assign({}, props, {
      content: invokeWithArgsOrReturn(props.content, [reference])
    }, props.ignoreAttributes ? {} : getDataAttributeProps(reference, props.plugins));
    out.aria = Object.assign({}, defaultProps.aria, {}, out.aria);
    out.aria = {
      expanded: out.aria.expanded === 'auto' ? props.interactive : out.aria.expanded,
      content: out.aria.content === 'auto' ? props.interactive ? null : 'describedby' : out.aria.content
    };
    return out;
  }
  function validateProps(partialProps, plugins) {
    if (partialProps === void 0) {
      partialProps = {};
    }

    if (plugins === void 0) {
      plugins = [];
    }

    var keys = Object.keys(partialProps);
    keys.forEach(function (prop) {
      var nonPluginProps = removeProperties(defaultProps, Object.keys(pluginProps));
      var didPassUnknownProp = !hasOwnProperty(nonPluginProps, prop); // Check if the prop exists in `plugins`

      if (didPassUnknownProp) {
        didPassUnknownProp = plugins.filter(function (plugin) {
          return plugin.name === prop;
        }).length === 0;
      }

      warnWhen(didPassUnknownProp, ["`" + prop + "`", "is not a valid prop. You may have spelled it incorrectly, or if it's", 'a plugin, forgot to pass it in an array as props.plugins.', '\n\n', 'All props: https://atomiks.github.io/tippyjs/v6/all-props/\n', 'Plugins: https://atomiks.github.io/tippyjs/v6/plugins/'].join(' '));
    });
  }

  var innerHTML = function innerHTML() {
    return 'innerHTML';
  };

  function dangerouslySetInnerHTML(element, html) {
    element[innerHTML()] = html;
  }

  function createArrowElement(value) {
    var arrow = div();

    if (value === true) {
      arrow.className = ARROW_CLASS;
    } else {
      arrow.className = SVG_ARROW_CLASS;

      if (isElement$1(value)) {
        arrow.appendChild(value);
      } else {
        dangerouslySetInnerHTML(arrow, value);
      }
    }

    return arrow;
  }

  function setContent(content, props) {
    if (isElement$1(props.content)) {
      dangerouslySetInnerHTML(content, '');
      content.appendChild(props.content);
    } else if (typeof props.content !== 'function') {
      if (props.allowHTML) {
        dangerouslySetInnerHTML(content, props.content);
      } else {
        content.textContent = props.content;
      }
    }
  }
  function getChildren(popper) {
    var box = popper.firstElementChild;
    var boxChildren = arrayFrom(box.children);
    return {
      box: box,
      content: boxChildren.find(function (node) {
        return node.classList.contains(CONTENT_CLASS);
      }),
      arrow: boxChildren.find(function (node) {
        return node.classList.contains(ARROW_CLASS) || node.classList.contains(SVG_ARROW_CLASS);
      }),
      backdrop: boxChildren.find(function (node) {
        return node.classList.contains(BACKDROP_CLASS);
      })
    };
  }
  function render(instance) {
    var popper = div();
    var box = div();
    box.className = BOX_CLASS;
    box.setAttribute('data-state', 'hidden');
    box.setAttribute('tabindex', '-1');
    var content = div();
    content.className = CONTENT_CLASS;
    content.setAttribute('data-state', 'hidden');
    setContent(content, instance.props);
    popper.appendChild(box);
    box.appendChild(content);
    onUpdate(instance.props, instance.props);

    function onUpdate(prevProps, nextProps) {
      var _getChildren = getChildren(popper),
          box = _getChildren.box,
          content = _getChildren.content,
          arrow = _getChildren.arrow;

      if (nextProps.theme) {
        box.setAttribute('data-theme', nextProps.theme);
      } else {
        box.removeAttribute('data-theme');
      }

      if (typeof nextProps.animation === 'string') {
        box.setAttribute('data-animation', nextProps.animation);
      } else {
        box.removeAttribute('data-animation');
      }

      if (nextProps.inertia) {
        box.setAttribute('data-inertia', '');
      } else {
        box.removeAttribute('data-inertia');
      }

      box.style.maxWidth = typeof nextProps.maxWidth === 'number' ? nextProps.maxWidth + "px" : nextProps.maxWidth;

      if (nextProps.role) {
        box.setAttribute('role', nextProps.role);
      } else {
        box.removeAttribute('role');
      }

      if (prevProps.content !== nextProps.content || prevProps.allowHTML !== nextProps.allowHTML) {
        setContent(content, instance.props);
      }

      if (nextProps.arrow) {
        if (!arrow) {
          box.appendChild(createArrowElement(nextProps.arrow));
        } else if (prevProps.arrow !== nextProps.arrow) {
          box.removeChild(arrow);
          box.appendChild(createArrowElement(nextProps.arrow));
        }
      } else if (arrow) {
        box.removeChild(arrow);
      }
    }

    return {
      popper: popper,
      onUpdate: onUpdate
    };
  } // Runtime check to identify if the render function is the default one; this
  // way we can apply default CSS transitions logic and it can be tree-shaken away

  render.$$tippy = true;

  var idCounter = 1;
  var mouseMoveListeners = []; // Used by `hideAll()`

  var mountedInstances = [];
  function createTippy(reference, passedProps) {
    var props = evaluateProps(reference, Object.assign({}, defaultProps, {}, getExtendedPassedProps(removeUndefinedProps(passedProps)))); // ===========================================================================
    // 🔒 Private members
    // ===========================================================================

    var showTimeout;
    var hideTimeout;
    var scheduleHideAnimationFrame;
    var isVisibleFromClick = false;
    var didHideDueToDocumentMouseDown = false;
    var didTouchMove = false;
    var ignoreOnFirstUpdate = false;
    var lastTriggerEvent;
    var currentTransitionEndListener;
    var onFirstUpdate;
    var listeners = [];
    var debouncedOnMouseMove = debounce$1(onMouseMove, props.interactiveDebounce);
    var currentTarget; // ===========================================================================
    // 🔑 Public members
    // ===========================================================================

    var id = idCounter++;
    var popperInstance = null;
    var plugins = unique(props.plugins);
    var state = {
      // Is the instance currently enabled?
      isEnabled: true,
      // Is the tippy currently showing and not transitioning out?
      isVisible: false,
      // Has the instance been destroyed?
      isDestroyed: false,
      // Is the tippy currently mounted to the DOM?
      isMounted: false,
      // Has the tippy finished transitioning in?
      isShown: false
    };
    var instance = {
      // properties
      id: id,
      reference: reference,
      popper: div(),
      popperInstance: popperInstance,
      props: props,
      state: state,
      plugins: plugins,
      // methods
      clearDelayTimeouts: clearDelayTimeouts,
      setProps: setProps,
      setContent: setContent,
      show: show,
      hide: hide,
      hideWithInteractivity: hideWithInteractivity,
      enable: enable,
      disable: disable,
      unmount: unmount,
      destroy: destroy
    }; // TODO: Investigate why this early return causes a TDZ error in the tests —
    // it doesn't seem to happen in the browser

    /* istanbul ignore if */

    if (!props.render) {
      {
        errorWhen(true, 'render() function has not been supplied.');
      }

      return instance;
    } // ===========================================================================
    // Initial mutations
    // ===========================================================================


    var _props$render = props.render(instance),
        popper = _props$render.popper,
        onUpdate = _props$render.onUpdate;

    popper.setAttribute('data-tippy-root', '');
    popper.id = "tippy-" + instance.id;
    instance.popper = popper;
    reference._tippy = instance;
    popper._tippy = instance;
    var pluginsHooks = plugins.map(function (plugin) {
      return plugin.fn(instance);
    });
    var hasAriaExpanded = reference.hasAttribute('aria-expanded');
    addListeners();
    handleAriaExpandedAttribute();
    handleStyles();
    invokeHook('onCreate', [instance]);

    if (props.showOnCreate) {
      scheduleShow();
    } // Prevent a tippy with a delay from hiding if the cursor left then returned
    // before it started hiding


    popper.addEventListener('mouseenter', function () {
      if (instance.props.interactive && instance.state.isVisible) {
        instance.clearDelayTimeouts();
      }
    });
    popper.addEventListener('mouseleave', function (event) {
      if (instance.props.interactive && instance.props.trigger.indexOf('mouseenter') >= 0) {
        getDocument().addEventListener('mousemove', debouncedOnMouseMove);
        debouncedOnMouseMove(event);
      }
    });
    return instance; // ===========================================================================
    // 🔒 Private methods
    // ===========================================================================

    function getNormalizedTouchSettings() {
      var touch = instance.props.touch;
      return Array.isArray(touch) ? touch : [touch, 0];
    }

    function getIsCustomTouchBehavior() {
      return getNormalizedTouchSettings()[0] === 'hold';
    }

    function getIsDefaultRenderFn() {
      var _instance$props$rende;

      // @ts-ignore
      return !!((_instance$props$rende = instance.props.render) == null ? void 0 : _instance$props$rende.$$tippy);
    }

    function getCurrentTarget() {
      return currentTarget || reference;
    }

    function getDocument() {
      var parent = getCurrentTarget().parentNode;
      return parent ? getOwnerDocument(parent) : document;
    }

    function getDefaultTemplateChildren() {
      return getChildren(popper);
    }

    function getDelay(isShow) {
      // For touch or keyboard input, force `0` delay for UX reasons
      // Also if the instance is mounted but not visible (transitioning out),
      // ignore delay
      if (instance.state.isMounted && !instance.state.isVisible || currentInput.isTouch || lastTriggerEvent && lastTriggerEvent.type === 'focus') {
        return 0;
      }

      return getValueAtIndexOrReturn(instance.props.delay, isShow ? 0 : 1, defaultProps.delay);
    }

    function handleStyles() {
      popper.style.pointerEvents = instance.props.interactive && instance.state.isVisible ? '' : 'none';
      popper.style.zIndex = "" + instance.props.zIndex;
    }

    function invokeHook(hook, args, shouldInvokePropsHook) {
      if (shouldInvokePropsHook === void 0) {
        shouldInvokePropsHook = true;
      }

      pluginsHooks.forEach(function (pluginHooks) {
        if (pluginHooks[hook]) {
          pluginHooks[hook].apply(void 0, args);
        }
      });

      if (shouldInvokePropsHook) {
        var _instance$props;

        (_instance$props = instance.props)[hook].apply(_instance$props, args);
      }
    }

    function handleAriaContentAttribute() {
      var aria = instance.props.aria;

      if (!aria.content) {
        return;
      }

      var attr = "aria-" + aria.content;
      var id = popper.id;
      var nodes = normalizeToArray(instance.props.triggerTarget || reference);
      nodes.forEach(function (node) {
        var currentValue = node.getAttribute(attr);

        if (instance.state.isVisible) {
          node.setAttribute(attr, currentValue ? currentValue + " " + id : id);
        } else {
          var nextValue = currentValue && currentValue.replace(id, '').trim();

          if (nextValue) {
            node.setAttribute(attr, nextValue);
          } else {
            node.removeAttribute(attr);
          }
        }
      });
    }

    function handleAriaExpandedAttribute() {
      if (hasAriaExpanded || !instance.props.aria.expanded) {
        return;
      }

      var nodes = normalizeToArray(instance.props.triggerTarget || reference);
      nodes.forEach(function (node) {
        if (instance.props.interactive) {
          node.setAttribute('aria-expanded', instance.state.isVisible && node === getCurrentTarget() ? 'true' : 'false');
        } else {
          node.removeAttribute('aria-expanded');
        }
      });
    }

    function cleanupInteractiveMouseListeners() {
      getDocument().removeEventListener('mousemove', debouncedOnMouseMove);
      mouseMoveListeners = mouseMoveListeners.filter(function (listener) {
        return listener !== debouncedOnMouseMove;
      });
    }

    function onDocumentPress(event) {
      // Moved finger to scroll instead of an intentional tap outside
      if (currentInput.isTouch) {
        if (didTouchMove || event.type === 'mousedown') {
          return;
        }
      } // Clicked on interactive popper


      if (instance.props.interactive && popper.contains(event.target)) {
        return;
      } // Clicked on the event listeners target


      if (getCurrentTarget().contains(event.target)) {
        if (currentInput.isTouch) {
          return;
        }

        if (instance.state.isVisible && instance.props.trigger.indexOf('click') >= 0) {
          return;
        }
      } else {
        invokeHook('onClickOutside', [instance, event]);
      }

      if (instance.props.hideOnClick === true) {
        instance.clearDelayTimeouts();
        instance.hide(); // `mousedown` event is fired right before `focus` if pressing the
        // currentTarget. This lets a tippy with `focus` trigger know that it
        // should not show

        didHideDueToDocumentMouseDown = true;
        setTimeout(function () {
          didHideDueToDocumentMouseDown = false;
        }); // The listener gets added in `scheduleShow()`, but this may be hiding it
        // before it shows, and hide()'s early bail-out behavior can prevent it
        // from being cleaned up

        if (!instance.state.isMounted) {
          removeDocumentPress();
        }
      }
    }

    function onTouchMove() {
      didTouchMove = true;
    }

    function onTouchStart() {
      didTouchMove = false;
    }

    function addDocumentPress() {
      var doc = getDocument();
      doc.addEventListener('mousedown', onDocumentPress, true);
      doc.addEventListener('touchend', onDocumentPress, TOUCH_OPTIONS);
      doc.addEventListener('touchstart', onTouchStart, TOUCH_OPTIONS);
      doc.addEventListener('touchmove', onTouchMove, TOUCH_OPTIONS);
    }

    function removeDocumentPress() {
      var doc = getDocument();
      doc.removeEventListener('mousedown', onDocumentPress, true);
      doc.removeEventListener('touchend', onDocumentPress, TOUCH_OPTIONS);
      doc.removeEventListener('touchstart', onTouchStart, TOUCH_OPTIONS);
      doc.removeEventListener('touchmove', onTouchMove, TOUCH_OPTIONS);
    }

    function onTransitionedOut(duration, callback) {
      onTransitionEnd(duration, function () {
        if (!instance.state.isVisible && popper.parentNode && popper.parentNode.contains(popper)) {
          callback();
        }
      });
    }

    function onTransitionedIn(duration, callback) {
      onTransitionEnd(duration, callback);
    }

    function onTransitionEnd(duration, callback) {
      var box = getDefaultTemplateChildren().box;

      function listener(event) {
        if (event.target === box) {
          updateTransitionEndListener(box, 'remove', listener);
          callback();
        }
      } // Make callback synchronous if duration is 0
      // `transitionend` won't fire otherwise


      if (duration === 0) {
        return callback();
      }

      updateTransitionEndListener(box, 'remove', currentTransitionEndListener);
      updateTransitionEndListener(box, 'add', listener);
      currentTransitionEndListener = listener;
    }

    function on(eventType, handler, options) {
      if (options === void 0) {
        options = false;
      }

      var nodes = normalizeToArray(instance.props.triggerTarget || reference);
      nodes.forEach(function (node) {
        node.addEventListener(eventType, handler, options);
        listeners.push({
          node: node,
          eventType: eventType,
          handler: handler,
          options: options
        });
      });
    }

    function addListeners() {
      if (getIsCustomTouchBehavior()) {
        on('touchstart', onTrigger, {
          passive: true
        });
        on('touchend', onMouseLeave, {
          passive: true
        });
      }

      splitBySpaces(instance.props.trigger).forEach(function (eventType) {
        if (eventType === 'manual') {
          return;
        }

        on(eventType, onTrigger);

        switch (eventType) {
          case 'mouseenter':
            on('mouseleave', onMouseLeave);
            break;

          case 'focus':
            on(isIE ? 'focusout' : 'blur', onBlurOrFocusOut);
            break;

          case 'focusin':
            on('focusout', onBlurOrFocusOut);
            break;
        }
      });
    }

    function removeListeners() {
      listeners.forEach(function (_ref) {
        var node = _ref.node,
            eventType = _ref.eventType,
            handler = _ref.handler,
            options = _ref.options;
        node.removeEventListener(eventType, handler, options);
      });
      listeners = [];
    }

    function onTrigger(event) {
      var _lastTriggerEvent;

      var shouldScheduleClickHide = false;

      if (!instance.state.isEnabled || isEventListenerStopped(event) || didHideDueToDocumentMouseDown) {
        return;
      }

      var wasFocused = ((_lastTriggerEvent = lastTriggerEvent) == null ? void 0 : _lastTriggerEvent.type) === 'focus';
      lastTriggerEvent = event;
      currentTarget = event.currentTarget;
      handleAriaExpandedAttribute();

      if (!instance.state.isVisible && isMouseEvent(event)) {
        // If scrolling, `mouseenter` events can be fired if the cursor lands
        // over a new target, but `mousemove` events don't get fired. This
        // causes interactive tooltips to get stuck open until the cursor is
        // moved
        mouseMoveListeners.forEach(function (listener) {
          return listener(event);
        });
      } // Toggle show/hide when clicking click-triggered tooltips


      if (event.type === 'click' && (instance.props.trigger.indexOf('mouseenter') < 0 || isVisibleFromClick) && instance.props.hideOnClick !== false && instance.state.isVisible) {
        shouldScheduleClickHide = true;
      } else {
        scheduleShow(event);
      }

      if (event.type === 'click') {
        isVisibleFromClick = !shouldScheduleClickHide;
      }

      if (shouldScheduleClickHide && !wasFocused) {
        scheduleHide(event);
      }
    }

    function onMouseMove(event) {
      var target = event.target;
      var isCursorOverReferenceOrPopper = getCurrentTarget().contains(target) || popper.contains(target);

      if (event.type === 'mousemove' && isCursorOverReferenceOrPopper) {
        return;
      }

      var popperTreeData = getNestedPopperTree().concat(popper).map(function (popper) {
        var _instance$popperInsta;

        var instance = popper._tippy;
        var state = (_instance$popperInsta = instance.popperInstance) == null ? void 0 : _instance$popperInsta.state;

        if (state) {
          return {
            popperRect: popper.getBoundingClientRect(),
            popperState: state,
            props: props
          };
        }

        return null;
      }).filter(Boolean);

      if (isCursorOutsideInteractiveBorder(popperTreeData, event)) {
        cleanupInteractiveMouseListeners();
        scheduleHide(event);
      }
    }

    function onMouseLeave(event) {
      var shouldBail = isEventListenerStopped(event) || instance.props.trigger.indexOf('click') >= 0 && isVisibleFromClick;

      if (shouldBail) {
        return;
      }

      if (instance.props.interactive) {
        instance.hideWithInteractivity(event);
        return;
      }

      scheduleHide(event);
    }

    function onBlurOrFocusOut(event) {
      if (instance.props.trigger.indexOf('focusin') < 0 && event.target !== getCurrentTarget()) {
        return;
      } // If focus was moved to within the popper


      if (instance.props.interactive && event.relatedTarget && popper.contains(event.relatedTarget)) {
        return;
      }

      scheduleHide(event);
    }

    function isEventListenerStopped(event) {
      return currentInput.isTouch ? getIsCustomTouchBehavior() !== event.type.indexOf('touch') >= 0 : false;
    }

    function createPopperInstance() {
      destroyPopperInstance();
      var _instance$props2 = instance.props,
          popperOptions = _instance$props2.popperOptions,
          placement = _instance$props2.placement,
          offset = _instance$props2.offset,
          getReferenceClientRect = _instance$props2.getReferenceClientRect,
          moveTransition = _instance$props2.moveTransition;
      var arrow = getIsDefaultRenderFn() ? getChildren(popper).arrow : null;
      var computedReference = getReferenceClientRect ? {
        getBoundingClientRect: getReferenceClientRect,
        contextElement: getReferenceClientRect.contextElement || getCurrentTarget()
      } : reference;
      var tippyModifier = {
        name: '$$tippy',
        enabled: true,
        phase: 'beforeWrite',
        requires: ['computeStyles'],
        fn: function fn(_ref2) {
          var state = _ref2.state;

          if (getIsDefaultRenderFn()) {
            var _getDefaultTemplateCh = getDefaultTemplateChildren(),
                box = _getDefaultTemplateCh.box;

            ['placement', 'reference-hidden', 'escaped'].forEach(function (attr) {
              if (attr === 'placement') {
                box.setAttribute('data-placement', state.placement);
              } else {
                if (state.attributes.popper["data-popper-" + attr]) {
                  box.setAttribute("data-" + attr, '');
                } else {
                  box.removeAttribute("data-" + attr);
                }
              }
            });
            state.attributes.popper = {};
          }
        }
      };
      var modifiers = [{
        name: 'offset',
        options: {
          offset: offset
        }
      }, {
        name: 'preventOverflow',
        options: {
          padding: {
            top: 2,
            bottom: 2,
            left: 5,
            right: 5
          }
        }
      }, {
        name: 'flip',
        options: {
          padding: 5
        }
      }, {
        name: 'computeStyles',
        options: {
          adaptive: !moveTransition
        }
      }, tippyModifier];

      if (getIsDefaultRenderFn() && arrow) {
        modifiers.push({
          name: 'arrow',
          options: {
            element: arrow,
            padding: 3
          }
        });
      }

      modifiers.push.apply(modifiers, (popperOptions == null ? void 0 : popperOptions.modifiers) || []);
      instance.popperInstance = createPopper(computedReference, popper, Object.assign({}, popperOptions, {
        placement: placement,
        onFirstUpdate: onFirstUpdate,
        modifiers: modifiers
      }));
    }

    function destroyPopperInstance() {
      if (instance.popperInstance) {
        instance.popperInstance.destroy();
        instance.popperInstance = null;
      }
    }

    function mount() {
      var appendTo = instance.props.appendTo;
      var parentNode; // By default, we'll append the popper to the triggerTargets's parentNode so
      // it's directly after the reference element so the elements inside the
      // tippy can be tabbed to
      // If there are clipping issues, the user can specify a different appendTo
      // and ensure focus management is handled correctly manually

      var node = getCurrentTarget();

      if (instance.props.interactive && appendTo === defaultProps.appendTo || appendTo === 'parent') {
        parentNode = node.parentNode;
      } else {
        parentNode = invokeWithArgsOrReturn(appendTo, [node]);
      } // The popper element needs to exist on the DOM before its position can be
      // updated as Popper needs to read its dimensions


      if (!parentNode.contains(popper)) {
        parentNode.appendChild(popper);
      }

      createPopperInstance();
      /* istanbul ignore else */

      {
        // Accessibility check
        warnWhen(instance.props.interactive && appendTo === defaultProps.appendTo && node.nextElementSibling !== popper, ['Interactive tippy element may not be accessible via keyboard', 'navigation because it is not directly after the reference element', 'in the DOM source order.', '\n\n', 'Using a wrapper <div> or <span> tag around the reference element', 'solves this by creating a new parentNode context.', '\n\n', 'Specifying `appendTo: document.body` silences this warning, but it', 'assumes you are using a focus management solution to handle', 'keyboard navigation.', '\n\n', 'See: https://atomiks.github.io/tippyjs/v6/accessibility/#interactivity'].join(' '));
      }
    }

    function getNestedPopperTree() {
      return arrayFrom(popper.querySelectorAll('[data-tippy-root]'));
    }

    function scheduleShow(event) {
      instance.clearDelayTimeouts();

      if (event) {
        invokeHook('onTrigger', [instance, event]);
      }

      addDocumentPress();
      var delay = getDelay(true);

      var _getNormalizedTouchSe = getNormalizedTouchSettings(),
          touchValue = _getNormalizedTouchSe[0],
          touchDelay = _getNormalizedTouchSe[1];

      if (currentInput.isTouch && touchValue === 'hold' && touchDelay) {
        delay = touchDelay;
      }

      if (delay) {
        showTimeout = setTimeout(function () {
          instance.show();
        }, delay);
      } else {
        instance.show();
      }
    }

    function scheduleHide(event) {
      instance.clearDelayTimeouts();
      invokeHook('onUntrigger', [instance, event]);

      if (!instance.state.isVisible) {
        removeDocumentPress();
        return;
      } // For interactive tippies, scheduleHide is added to a document.body handler
      // from onMouseLeave so must intercept scheduled hides from mousemove/leave
      // events when trigger contains mouseenter and click, and the tip is
      // currently shown as a result of a click.


      if (instance.props.trigger.indexOf('mouseenter') >= 0 && instance.props.trigger.indexOf('click') >= 0 && ['mouseleave', 'mousemove'].indexOf(event.type) >= 0 && isVisibleFromClick) {
        return;
      }

      var delay = getDelay(false);

      if (delay) {
        hideTimeout = setTimeout(function () {
          if (instance.state.isVisible) {
            instance.hide();
          }
        }, delay);
      } else {
        // Fixes a `transitionend` problem when it fires 1 frame too
        // late sometimes, we don't want hide() to be called.
        scheduleHideAnimationFrame = requestAnimationFrame(function () {
          instance.hide();
        });
      }
    } // ===========================================================================
    // 🔑 Public methods
    // ===========================================================================


    function enable() {
      instance.state.isEnabled = true;
    }

    function disable() {
      // Disabling the instance should also hide it
      // https://github.com/atomiks/tippy.js-react/issues/106
      instance.hide();
      instance.state.isEnabled = false;
    }

    function clearDelayTimeouts() {
      clearTimeout(showTimeout);
      clearTimeout(hideTimeout);
      cancelAnimationFrame(scheduleHideAnimationFrame);
    }

    function setProps(partialProps) {
      /* istanbul ignore else */
      {
        warnWhen(instance.state.isDestroyed, createMemoryLeakWarning('setProps'));
      }

      if (instance.state.isDestroyed) {
        return;
      }

      invokeHook('onBeforeUpdate', [instance, partialProps]);
      removeListeners();
      var prevProps = instance.props;
      var nextProps = evaluateProps(reference, Object.assign({}, instance.props, {}, partialProps, {
        ignoreAttributes: true
      }));
      instance.props = nextProps;
      addListeners();

      if (prevProps.interactiveDebounce !== nextProps.interactiveDebounce) {
        cleanupInteractiveMouseListeners();
        debouncedOnMouseMove = debounce$1(onMouseMove, nextProps.interactiveDebounce);
      } // Ensure stale aria-expanded attributes are removed


      if (prevProps.triggerTarget && !nextProps.triggerTarget) {
        normalizeToArray(prevProps.triggerTarget).forEach(function (node) {
          node.removeAttribute('aria-expanded');
        });
      } else if (nextProps.triggerTarget) {
        reference.removeAttribute('aria-expanded');
      }

      handleAriaExpandedAttribute();
      handleStyles();

      if (onUpdate) {
        onUpdate(prevProps, nextProps);
      }

      if (instance.popperInstance) {
        createPopperInstance(); // Fixes an issue with nested tippies if they are all getting re-rendered,
        // and the nested ones get re-rendered first.
        // https://github.com/atomiks/tippyjs-react/issues/177
        // TODO: find a cleaner / more efficient solution(!)

        getNestedPopperTree().forEach(function (nestedPopper) {
          // React (and other UI libs likely) requires a rAF wrapper as it flushes
          // its work in one
          requestAnimationFrame(nestedPopper._tippy.popperInstance.forceUpdate);
        });
      }

      invokeHook('onAfterUpdate', [instance, partialProps]);
    }

    function setContent(content) {
      instance.setProps({
        content: content
      });
    }

    function show() {
      /* istanbul ignore else */
      {
        warnWhen(instance.state.isDestroyed, createMemoryLeakWarning('show'));
      } // Early bail-out


      var isAlreadyVisible = instance.state.isVisible;
      var isDestroyed = instance.state.isDestroyed;
      var isDisabled = !instance.state.isEnabled;
      var isTouchAndTouchDisabled = currentInput.isTouch && !instance.props.touch;
      var duration = getValueAtIndexOrReturn(instance.props.duration, 0, defaultProps.duration);

      if (isAlreadyVisible || isDestroyed || isDisabled || isTouchAndTouchDisabled) {
        return;
      } // Normalize `disabled` behavior across browsers.
      // Firefox allows events on disabled elements, but Chrome doesn't.
      // Using a wrapper element (i.e. <span>) is recommended.


      if (getCurrentTarget().hasAttribute('disabled')) {
        return;
      }

      invokeHook('onShow', [instance], false);

      if (instance.props.onShow(instance) === false) {
        return;
      }

      instance.state.isVisible = true;

      if (getIsDefaultRenderFn()) {
        popper.style.visibility = 'visible';
      }

      handleStyles();
      addDocumentPress();

      if (!instance.state.isMounted) {
        popper.style.transition = 'none';
      } // If flipping to the opposite side after hiding at least once, the
      // animation will use the wrong placement without resetting the duration


      if (getIsDefaultRenderFn()) {
        var _getDefaultTemplateCh2 = getDefaultTemplateChildren(),
            box = _getDefaultTemplateCh2.box,
            content = _getDefaultTemplateCh2.content;

        setTransitionDuration([box, content], 0);
      }

      onFirstUpdate = function onFirstUpdate() {
        if (!instance.state.isVisible || ignoreOnFirstUpdate) {
          return;
        }

        ignoreOnFirstUpdate = true; // reflow

        void popper.offsetHeight;
        popper.style.transition = instance.props.moveTransition;

        if (getIsDefaultRenderFn() && instance.props.animation) {
          var _getDefaultTemplateCh3 = getDefaultTemplateChildren(),
              _box = _getDefaultTemplateCh3.box,
              _content = _getDefaultTemplateCh3.content;

          setTransitionDuration([_box, _content], duration);
          setVisibilityState([_box, _content], 'visible');
        }

        handleAriaContentAttribute();
        handleAriaExpandedAttribute();
        pushIfUnique(mountedInstances, instance);
        instance.state.isMounted = true;
        invokeHook('onMount', [instance]);

        if (instance.props.animation && getIsDefaultRenderFn()) {
          onTransitionedIn(duration, function () {
            instance.state.isShown = true;
            invokeHook('onShown', [instance]);
          });
        }
      };

      mount();
    }

    function hide() {
      /* istanbul ignore else */
      {
        warnWhen(instance.state.isDestroyed, createMemoryLeakWarning('hide'));
      } // Early bail-out


      var isAlreadyHidden = !instance.state.isVisible;
      var isDestroyed = instance.state.isDestroyed;
      var isDisabled = !instance.state.isEnabled;
      var duration = getValueAtIndexOrReturn(instance.props.duration, 1, defaultProps.duration);

      if (isAlreadyHidden || isDestroyed || isDisabled) {
        return;
      }

      invokeHook('onHide', [instance], false);

      if (instance.props.onHide(instance) === false) {
        return;
      }

      instance.state.isVisible = false;
      instance.state.isShown = false;
      ignoreOnFirstUpdate = false;
      isVisibleFromClick = false;

      if (getIsDefaultRenderFn()) {
        popper.style.visibility = 'hidden';
      }

      cleanupInteractiveMouseListeners();
      removeDocumentPress();
      handleStyles();

      if (getIsDefaultRenderFn()) {
        var _getDefaultTemplateCh4 = getDefaultTemplateChildren(),
            box = _getDefaultTemplateCh4.box,
            content = _getDefaultTemplateCh4.content;

        if (instance.props.animation) {
          setTransitionDuration([box, content], duration);
          setVisibilityState([box, content], 'hidden');
        }
      }

      handleAriaContentAttribute();
      handleAriaExpandedAttribute();

      if (instance.props.animation) {
        if (getIsDefaultRenderFn()) {
          onTransitionedOut(duration, instance.unmount);
        }
      } else {
        instance.unmount();
      }
    }

    function hideWithInteractivity(event) {
      /* istanbul ignore else */
      {
        warnWhen(instance.state.isDestroyed, createMemoryLeakWarning('hideWithInteractivity'));
      }

      getDocument().addEventListener('mousemove', debouncedOnMouseMove);
      pushIfUnique(mouseMoveListeners, debouncedOnMouseMove);
      debouncedOnMouseMove(event);
    }

    function unmount() {
      /* istanbul ignore else */
      {
        warnWhen(instance.state.isDestroyed, createMemoryLeakWarning('unmount'));
      }

      if (instance.state.isVisible) {
        instance.hide();
      }

      if (!instance.state.isMounted) {
        return;
      }

      destroyPopperInstance(); // If a popper is not interactive, it will be appended outside the popper
      // tree by default. This seems mainly for interactive tippies, but we should
      // find a workaround if possible

      getNestedPopperTree().forEach(function (nestedPopper) {
        nestedPopper._tippy.unmount();
      });

      if (popper.parentNode) {
        popper.parentNode.removeChild(popper);
      }

      mountedInstances = mountedInstances.filter(function (i) {
        return i !== instance;
      });
      instance.state.isMounted = false;
      invokeHook('onHidden', [instance]);
    }

    function destroy() {
      /* istanbul ignore else */
      {
        warnWhen(instance.state.isDestroyed, createMemoryLeakWarning('destroy'));
      }

      if (instance.state.isDestroyed) {
        return;
      }

      instance.clearDelayTimeouts();
      instance.unmount();
      removeListeners();
      delete reference._tippy;
      instance.state.isDestroyed = true;
      invokeHook('onDestroy', [instance]);
    }
  }

  function tippy(targets, optionalProps) {
    if (optionalProps === void 0) {
      optionalProps = {};
    }

    var plugins = defaultProps.plugins.concat(optionalProps.plugins || []);
    /* istanbul ignore else */

    {
      validateTargets(targets);
      validateProps(optionalProps, plugins);
    }

    bindGlobalEventListeners();
    var passedProps = Object.assign({}, optionalProps, {
      plugins: plugins
    });
    var elements = getArrayOfElements(targets);
    /* istanbul ignore else */

    {
      var isSingleContentElement = isElement$1(passedProps.content);
      var isMoreThanOneReferenceElement = elements.length > 1;
      warnWhen(isSingleContentElement && isMoreThanOneReferenceElement, ['tippy() was passed an Element as the `content` prop, but more than', 'one tippy instance was created by this invocation. This means the', 'content element will only be appended to the last tippy instance.', '\n\n', 'Instead, pass the .innerHTML of the element, or use a function that', 'returns a cloned version of the element instead.', '\n\n', '1) content: element.innerHTML\n', '2) content: () => element.cloneNode(true)'].join(' '));
    }

    var instances = elements.reduce(function (acc, reference) {
      var instance = reference && createTippy(reference, passedProps);

      if (instance) {
        acc.push(instance);
      }

      return acc;
    }, []);
    return isElement$1(targets) ? instances[0] : instances;
  }

  tippy.defaultProps = defaultProps;
  tippy.setDefaultProps = setDefaultProps;
  tippy.currentInput = currentInput;

  var BUBBLING_EVENTS_MAP = {
    mouseover: 'mouseenter',
    focusin: 'focus',
    click: 'click'
  };
  /**
   * Creates a delegate instance that controls the creation of tippy instances
   * for child elements (`target` CSS selector).
   */

  function delegate(targets, props) {
    /* istanbul ignore else */
    {
      errorWhen(!(props && props.target), ['You must specity a `target` prop indicating a CSS selector string matching', 'the target elements that should receive a tippy.'].join(' '));
    }

    var listeners = [];
    var childTippyInstances = [];
    var disabled = false;
    var target = props.target;
    var nativeProps = removeProperties(props, ['target']);
    var parentProps = Object.assign({}, nativeProps, {
      trigger: 'manual',
      touch: false
    });
    var childProps = Object.assign({}, nativeProps, {
      showOnCreate: true
    });
    var returnValue = tippy(targets, parentProps);
    var normalizedReturnValue = normalizeToArray(returnValue);

    function onTrigger(event) {
      if (!event.target || disabled) {
        return;
      }

      var targetNode = event.target.closest(target);

      if (!targetNode) {
        return;
      } // Get relevant trigger with fallbacks:
      // 1. Check `data-tippy-trigger` attribute on target node
      // 2. Fallback to `trigger` passed to `delegate()`
      // 3. Fallback to `defaultProps.trigger`


      var trigger = targetNode.getAttribute('data-tippy-trigger') || props.trigger || defaultProps.trigger; // @ts-ignore

      if (targetNode._tippy) {
        return;
      }

      if (event.type === 'touchstart' && typeof childProps.touch === 'boolean') {
        return;
      }

      if (event.type !== 'touchstart' && trigger.indexOf(BUBBLING_EVENTS_MAP[event.type]) < 0) {
        return;
      }

      var instance = tippy(targetNode, childProps);

      if (instance) {
        childTippyInstances = childTippyInstances.concat(instance);
      }
    }

    function on(node, eventType, handler, options) {
      if (options === void 0) {
        options = false;
      }

      node.addEventListener(eventType, handler, options);
      listeners.push({
        node: node,
        eventType: eventType,
        handler: handler,
        options: options
      });
    }

    function addEventListeners(instance) {
      var reference = instance.reference;
      on(reference, 'touchstart', onTrigger);
      on(reference, 'mouseover', onTrigger);
      on(reference, 'focusin', onTrigger);
      on(reference, 'click', onTrigger);
    }

    function removeEventListeners() {
      listeners.forEach(function (_ref) {
        var node = _ref.node,
            eventType = _ref.eventType,
            handler = _ref.handler,
            options = _ref.options;
        node.removeEventListener(eventType, handler, options);
      });
      listeners = [];
    }

    function applyMutations(instance) {
      var originalDestroy = instance.destroy;
      var originalEnable = instance.enable;
      var originalDisable = instance.disable;

      instance.destroy = function (shouldDestroyChildInstances) {
        if (shouldDestroyChildInstances === void 0) {
          shouldDestroyChildInstances = true;
        }

        if (shouldDestroyChildInstances) {
          childTippyInstances.forEach(function (instance) {
            instance.destroy();
          });
        }

        childTippyInstances = [];
        removeEventListeners();
        originalDestroy();
      };

      instance.enable = function () {
        originalEnable();
        childTippyInstances.forEach(function (instance) {
          return instance.enable();
        });
        disabled = false;
      };

      instance.disable = function () {
        originalDisable();
        childTippyInstances.forEach(function (instance) {
          return instance.disable();
        });
        disabled = true;
      };

      addEventListeners(instance);
    }

    normalizedReturnValue.forEach(applyMutations);
    return returnValue;
  }

  tippy.setDefaultProps({
    render: render
  });

  function init$a ($root) {
    delegate($root[0], {
      target: '.fa-hashtag, [title]',
      delay: [200, null], // show / hide delay (null = default)
      allowHTML: true,
      maxWidth: 'none',
      appendTo: function (reference) {
        return $(reference).closest('.group-body, .debug')[0]
      },
      content: tippyContent,
      onHide: tippyOnHide,
      onMount: tippyOnMount,
      onShow: tippyOnShow
    });
  }

  function tippyContent (reference) {
    var $ref = $(reference);
    var attributes;
    var title;
    if ($ref.hasClass('fa-hashtag')) {
      attributes = $ref.parent().data('attributes');
      return buildAttributes(attributes)
    }
    title = $ref.prop('title');
    if (!title) {
      return
    }
    $ref.data('titleOrig', title);
    if (title === 'Deprecated') {
      title = tippyContentDeprecated($ref, title);
    } else if (title === 'Inherited') {
      title = tippyContentInherited($ref, title);
    } else if (title === 'Open in editor') {
      title = '<i class="fa fa-pencil"></i> ' + title;
    } else if (title.match(/^\/.+: line \d+$/)) {
      title = '<i class="fa fa-file-code-o"></i> ' + title;
    }
    return title.replace(/\n/g, '<br />')
  }

  function tippyContentDeprecated ($ref, title) {
    var titleMore = $ref.parent().data('deprecatedDesc');
    return titleMore
      ? 'Deprecated: ' + titleMore
      : title
  }

  function tippyContentInherited ($ref, title) {
    var titleMore = $ref.parent().data('inheritedFrom');
    if (titleMore) {
      titleMore = '<span class="classname">' +
        titleMore.replace(/^(.*\\)(.+)$/, '<span class="namespace">$1</span>$2') +
        '</span>';
      title = 'Inherited from ' + titleMore;
    }
    return title
  }

  function tippyOnHide (instance) {
    var $ref = $(instance.reference);
    var title = $ref.data('titleOrig');
    if (title) {
      $ref.attr('title', title);
    }
    setTimeout(function () {
      instance.destroy();
    }, 100);
  }

  function tippyOnMount (instance) {
    var $ref = $(instance.reference);
    var modifiersNew = [
      {
        name: 'flip',
        options: {
          boundary: $ref.closest('.tab-panes')[0],
          padding: 5
        }
      },
      {
        name: 'preventOverflow',
        options: {
          boundary: $ref.closest('.tab-body')[0],
          padding: { top: 2, bottom: 2, left: 5, right: 5 }
        }
      }
    ];
    // console.log('popperInstance options before', instance.popperInstance.state.options)
    instance.popperInstance.setOptions({
      modifiers: mergeModifiers(instance.popperInstance.state.options.modifiers, modifiersNew)
    });
    // console.log('popperInstance options after', instance.popperInstance.state.options)
  }

  function tippyOnShow (instance) {
    var $ref = $(instance.reference);
    $ref.removeAttr('title');
    $ref.addClass('hasTooltip');
    $ref.parents('.hasTooltip').each(function () {
      if (this._tippy) {
        this._tippy.hide();
      }
    });
    return true
  }

  function mergeModifiers (modCur, modNew) {
    var i;
    var count;
    var modifier;
    var names = [];
    for (i = 0, count = modNew.length; i < count; i++) {
      modifier = modNew[i];
      names.push(modifier.name);
    }
    for (i = 0, count = modCur.length; i < count; i++) {
      modifier = modCur[i];
      if (names.indexOf(modifier.name) > -1) {
        continue
      }
      modNew.push(modifier);
    }
    return modNew
  }

  function buildAttributes (attributes) {
    var i;
    var count = attributes.length;
    var html = '<dl>' +
      '<dt class="attributes">attributes</dt>';
    for (i = 0; i < count; i++) {
      html += buildAttribute(attributes[i]);
    }
    html += '</dl>';
    return html
  }

  function buildAttribute (attribute) {
    var html = '<dd class="attribute">';
    var arg;
    var args = [];
    html += markupClassname(attribute.name);
    if (Object.keys(attribute.arguments).length) {
      $.each(attribute.arguments, function (i, val) {
        arg = i.match(/^\d+$/) === null
          ? '<span class="t_parameter-name">' + i + '</span><span class="t_punct">:</span>'
          : '';
        arg += dumpSimple(val);
        args.push(arg);
      });
      html += '<span class="t_punct">(</span>' +
        args.join('<span class="t_punct">,</span> ') +
        '<span class="t_punct">)</span>';
    }
    html += '</dd>';
    return html
  }

  function dumpSimple (val) {
    var type = 'string';
    if (typeof val === 'number') {
      type = val % 1 === 0
        ? 'int'
        : 'float';
    }
    if (typeof val === 'string' && val.length && val.match(/^\d*(\.\d+)?$/) !== null) {
      type = 'string numeric';
    }
    return '<span class="t_' + type + '">' + val + '</span>'
  }

  function markupClassname (val) {
    var matches = val.match(/^(.+\\)([^\\]+)$/);
    val = matches
      ? '<span class="namespace">' + matches[1] + '</span>' + matches[2]
      : val;
    return '<span class="classname">' + val + '</span>'
  }

  function Config (defaults, localStorageKey) {
    var storedConfig = null;
    if (defaults.useLocalStorage) {
      storedConfig = lsGet(localStorageKey);
    }
    this.config = $.extend({}, defaults, storedConfig || {});
    // console.warn('config', JSON.parse(JSON.stringify(this.config)))
    this.haveSavedConfig = typeof storedConfig === 'object';
    this.localStorageKey = localStorageKey;
    this.localStorageKeys = ['persistDrawer', 'openDrawer', 'openSidebar', 'height', 'linkFiles', 'linkFilesTemplate'];
  }

  Config.prototype.get = function (key) {
    if (typeof key === 'undefined') {
      return JSON.parse(JSON.stringify(this.config))
    }
    return typeof this.config[key] !== 'undefined'
      ? this.config[key]
      : null
  };

  Config.prototype.set = function (key, val) {
    var lsObj = {};
    var setVals = {};
    var haveLsKey = false;
    if (typeof key === 'object') {
      setVals = key;
    } else {
      setVals[key] = val;
    }
    // console.log('config.set', setVals)
    for (var k in setVals) {
      this.config[k] = setVals[k];
    }
    if (this.config.useLocalStorage) {
      lsObj = lsGet(this.localStorageKey) || {};
      if (setVals.linkFilesTemplateDefault && !lsObj.linkFilesTemplate) {
        // we don't have a user specified template... use the default
        this.config.linkFiles = setVals.linkFiles = true;
        this.config.linkFilesTemplate = setVals.linkFilesTemplate = setVals.linkFilesTemplateDefault;
      }
      for (var i = 0, count = this.localStorageKeys.length; i < count; i++) {
        key = this.localStorageKeys[i];
        if (typeof setVals[key] !== 'undefined') {
          haveLsKey = true;
          lsObj[key] = setVals[key];
        }
      }
      if (haveLsKey) {
        lsSet(this.localStorageKey, lsObj);
      }
    }
    this.haveSavedConfig = true;
  };

  function loadDeps (deps) {
    var checkInterval;
    var intervalCounter = 1;
    deps.reverse();
    if (document.getElementsByTagName('body')[0].childElementCount === 1) {
      // output only contains debug
      // don't wait for interval to begin
      loadDepsDoer(deps);
    } else {
      loadDepsDoer(deps, true);
    }
    checkInterval = setInterval(function () {
      loadDepsDoer(deps, intervalCounter === 10);
      if (deps.length === 0) {
        clearInterval(checkInterval);
      } else if (intervalCounter === 20) {
        clearInterval(checkInterval);
      }
      intervalCounter++;
    }, 500);
  }

  function addScript (src) {
    var firstScript = document.getElementsByTagName('script')[0];
    var jsNode = document.createElement('script');
    jsNode.src = src;
    firstScript.parentNode.insertBefore(jsNode, firstScript);
  }

  function addStylesheet (src) {
    var link = document.createElement('link');
    link.type = 'text/css';
    link.rel = 'stylesheet';
    link.href = src;
    document.head.appendChild(link);
  }

  function loadDepsDoer (deps, checkOnly) {
    var dep;
    var i;
    for (i = deps.length - 1; i >= 0; i--) {
      dep = deps[i];
      if (dep.check()) {
        // dependency exists
        onDepLoaded(dep);
        deps.splice(i, 1); // remove it
        continue
      } else if (dep.status !== 'loading' && !checkOnly) {
        dep.status = 'loading';
        addDep(dep);
      }
    }
  }

  function addDep (dep) {
    var type = dep.type || 'script';
    if (type === 'script') {
      addScript(dep.src);
    } else if (type === 'stylesheet') {
      addStylesheet(dep.src);
    }
  }

  function onDepLoaded (dep) {
    if (dep.onLoaded) {
      dep.onLoaded();
    }
  }

  /**
   * Enhance debug output
   *    Add expand/collapse functionality to groups, arrays, & objects
   *    Add FontAwesome icons
   */

  var listenersRegistered = false;
  var config$8 = new Config({
    fontAwesomeCss: '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
    clipboardSrc: '//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js',
    iconsExpand: {
      expand: 'fa-plus-square-o',
      collapse: 'fa-minus-square-o',
      empty: 'fa-square-o'
    },
    iconsMisc: {
      '.string-encoded': '<i class="fa fa-barcode"></i>',
      '.timestamp': '<i class="fa fa-calendar"></i>'
    },
    iconsArray: {
      '> .array-inner > li > .exclude-count': '<i class="fa fa-eye-slash"></i>'
    },
    iconsObject: {
      '> .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
      '> .info.magic': '<i class="fa fa-fw fa-magic"></i>',
      '> .inherited': '<i class="fa fa-fw fa-clone" title="Inherited"></i>',
      '> .method.isDeprecated': '<i class="fa fa-fw fa-arrow-down" title="Deprecated"></i>',
      '> .method > .t_modifier_magic': '<i class="fa fa-magic" title="magic method"></i>',
      '> .method > .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
      '> .method > .parameter.isPromoted': '<i class="fa fa-arrow-up" title="Promoted"></i>',
      '> .method > .parameter[data-attributes]': '<i class="fa fa-hashtag" title="Attributes"></i>',
      '> *[data-attributes]': '<i class="fa fa-hashtag" title="Attributes"></i>',
      '> .property.debuginfo-value': '<i class="fa fa-eye" title="via __debugInfo()"></i>',
      '> .property.debuginfo-excluded': '<i class="fa fa-eye-slash" title="not included in __debugInfo"></i>',
      '> .property.private-ancestor': '<i class="fa fa-lock" title="private ancestor"></i>',
      '> .property.isPromoted': '<i class="fa fa-arrow-up" title="Promoted"></i>',
      '> .property > .t_modifier_magic': '<i class="fa fa-magic" title="magic property"></i>',
      '> .property > .t_modifier_magic-read': '<i class="fa fa-magic" title="magic property"></i>',
      '> .property > .t_modifier_magic-write': '<i class="fa fa-magic" title="magic property"></i>',
      '[data-toggle=vis][data-vis=private]': '<i class="fa fa-user-secret"></i>',
      '[data-toggle=vis][data-vis=protected]': '<i class="fa fa-shield"></i>',
      '[data-toggle=vis][data-vis=debuginfo-excluded]': '<i class="fa fa-eye-slash"></i>',
      '[data-toggle=vis][data-vis=inherited]': '<i class="fa fa-clone"></i>'
    },
    // debug methods (not object methods)
    iconsMethods: {
      '.m_assert': '<i class="fa-lg"><b>&ne;</b></i>',
      '.m_clear': '<i class="fa fa-lg fa-ban"></i>',
      '.m_count': '<i class="fa fa-lg fa-plus-circle"></i>',
      '.m_countReset': '<i class="fa fa-lg fa-plus-circle"></i>',
      '.m_error': '<i class="fa fa-lg fa-times-circle"></i>',
      '.m_group.expanded': '<i class="fa fa-lg fa-minus-square-o"></i>',
      '.m_group': '<i class="fa fa-lg fa-plus-square-o"></i>',
      '.m_info': '<i class="fa fa-lg fa-info-circle"></i>',
      '.m_profile': '<i class="fa fa-lg fa-pie-chart"></i>',
      '.m_profileEnd': '<i class="fa fa-lg fa-pie-chart"></i>',
      '.m_time': '<i class="fa fa-lg fa-clock-o"></i>',
      '.m_timeLog': '<i class="fa fa-lg fa-clock-o"></i>',
      '.m_trace': '<i class="fa fa-list"></i>',
      '.m_warn': '<i class="fa fa-lg fa-warning"></i>'
    },
    debugKey: getDebugKey(),
    drawer: false,
    persistDrawer: false,
    linkFiles: false,
    linkFilesTemplate: 'subl://open?url=file://%file&line=%line',
    useLocalStorage: true,
    tooltip: true,
    cssFontAwesome5: '' +
      '.debug .fa-bell-o:before { content:"\\f0f3"; font-weight:400; }' +
      '.debug .fa-calendar:before { content:"\\f073"; }' +
      '.debug .fa-clock-o:before { content:"\\f017"; font-weight:400; }' +
      '.debug .fa-clone:before { content:"\\f24d"; font-weight:400; }' +
      '.debug .fa-envelope-o:before { content:"\\f0e0"; font-weight:400; }' +
      '.debug .fa-exchange:before { content:"\\f362"; }' +
      '.debug .fa-external-link:before { content:"\\f35d"; }' +
      '.debug .fa-eye-slash:before { content:"\\f070"; font-weight:400; }' +
      '.debug .fa-file-code-o:before { content:"\\f1c9"; font-weight:400; }' +
      '.debug .fa-file-text-o:before { content:"\\f15c"; font-weight:400; }' +
      '.debug .fa-files-o:before { content:"\\f0c5"; font-weight:400; }' +
      '.debug .fa-hand-stop-o:before { content:"\\f256"; font-weight:400; }' +
      '.debug .fa-minus-square-o:before { content:"\\f146"; font-weight:400; }' +
      '.debug .fa-pencil:before { content:"\\f303" }' +
      '.debug .fa-pie-chart:before { content:"\\f200"; }' +
      '.debug .fa-plus-square-o:before { content:"\\f0fe"; font-weight:400; }' +
      '.debug .fa-shield:before { content:"\\f3ed"; }' +
      '.debug .fa-square-o:before { content:"\\f0c8"; font-weight:400; }' +
      '.debug .fa-user-o:before { content:"\\f007"; }' +
      '.debug .fa-warning:before { content:"\\f071"; }' +
      '.debug .fa.fa-github { font-family: "Font Awesome 5 Brands"; }'
  }, 'phpDebugConsole');

  if (typeof $ === 'undefined') {
    throw new TypeError('PHPDebugConsole\'s JavaScript requires jQuery.')
  }

  /*
    Load 'optional' dependencies
  */
  loadDeps([
    {
      src: config$8.get('fontAwesomeCss'),
      type: 'stylesheet',
      check: function () {
        var fontFamily = getFontFamily();
        var haveFa = fontFamily === 'FontAwesome' || fontFamily.indexOf('Font Awesome') > -1;
        return haveFa
      },
      onLoaded: function () {
        var fontFamily = getFontFamily();
        var matches = fontFamily.match(/Font\s?Awesome.+(\d+)/);
        if (matches && matches[1] >= 5) {
          addStyle(config$8.get('cssFontAwesome5'));
        }
      }
    },
    {
      src: config$8.get('clipboardSrc'),
      check: function () {
        return typeof window.ClipboardJS !== 'undefined'
      },
      onLoaded: function () {
        initClipboardJs();
      }
    }
  ]);

  $.fn.debugEnhance = function (method, arg1, arg2) {
    // console.warn('debugEnhance', method, this)
    if (method === 'sidebar') {
      debugEnhanceSidebar(this, arg1);
    } else if (method === 'buildChannelList') {
      return buildChannelList(arg1, arg2, arguments[3])
    } else if (method === 'collapse') {
      this.each(function () {
        collapse($(this), arg1);
      });
    } else if (method === 'expand') {
      this.each(function () {
        expand($(this));
      });
    } else if (method === 'init') {
      debugEnhanceInit(this, arg1);
    } else if (method === 'setConfig') {
      debugEnhanceSetConfig(this, arg1);
    } else {
      debugEnhanceDefault(this);
    }
    return this
  };

  $(function () {
    $('.debug').each(function () {
      $(this).debugEnhance('init');
    });
  });

  function debugEnhanceInit ($node, arg1) {
    var conf = new Config(config$8.get(), 'phpDebugConsole');
    $node.data('config', conf);
    conf.set($node.eq(0).data('options') || {});
    if (typeof arg1 === 'object') {
      conf.set(arg1);
    }
    init$9($node);
    if (conf.get('tooltip')) {
      init$a($node);
    }
    init$2($node);
    init$8($node);
    registerListeners();
    init$7($node);
    if (!conf.get('drawer')) {
      $node.debugEnhance();
    }
  }

  function debugEnhanceDefault ($node) {
    $node.each(function () {
      var $self = $(this);
      if ($self.hasClass('debug')) {
        // console.warn('debugEnhance() : .debug')
        $self.find('.debug-menu-bar > nav, .tab-panes').show();
        $self.find('.tab-pane.active')
          .find('.m_alert, .debug-log-summary, .debug-log')
          .debugEnhance();
        return
      }
      if ($self.hasClass('filter-hidden') && $self.hasClass('m_group') === false) {
        return
      }
      // console.group('debugEnhance')
      if ($self.hasClass('group-body')) {
        enhanceEntries($self);
      } else if ($self.is('li, div') && $self.prop('class').match(/\bm_/) !== null) {
        // logEntry  (alerts use <div>)
        enhanceEntry($self);
      } else if ($self.prop('class').match(/\bt_/)) {
        // value
        enhanceValue(
          $self.parents('li').filter(function () {
            return $(this).prop('class').match(/\bm_/) !== null
          }),
          $self
        );
      }
      // console.groupEnd()
    });
  }

  function debugEnhanceSetConfig ($node, arg1) {
    if (typeof arg1 !== 'object') {
      return
    }
    config$8.set(arg1);
    // update log entries that have already been enhanced
    $node
      .find('.debug-log.enhanced')
      .closest('.debug')
      .trigger('config.debug.updated', 'linkFilesTemplate');
  }

  function debugEnhanceSidebar ($node, arg1) {
    if (arg1 === 'add') {
      addMarkup$1($node);
    } else if (arg1 === 'open') {
      open$2($node);
    } else if (arg1 === 'close') {
      close$2($node);
    }
  }

  /**
   * Add <style> tag to head of document
   */
  function addStyle (css) {
    var head = document.head || document.getElementsByTagName('head')[0];
    var style = document.createElement('style');
    style.type = 'text/css';
    head.appendChild(style);
    if (style.styleSheet) {
      // This is required for IE8 and below.
      style.styleSheet.cssText = css;
      return
    }
    style.appendChild(document.createTextNode(css));
  }

  /**
   * For given css class, what is its font-family
   */
  function getFontFamily (cssClass) {
    var span = document.createElement('span');
    var fontFamily = null;
    span.className = 'fa';
    span.style.display = 'none';
    document.body.appendChild(span);
    fontFamily = window.getComputedStyle(span, null).getPropertyValue('font-family');
    document.body.removeChild(span);
    return fontFamily
  }

  function getDebugKey () {
    var key = null;
    var queryParams = queryDecode();
    var cookieValue = cookieGet('debug');
    if (typeof queryParams.debug !== 'undefined') {
      key = queryParams.debug;
    } else if (cookieValue) {
      key = cookieValue;
    }
    return key
  }

  function initClipboardJs () {
    /*
      Copy strings/floats/ints to clipboard when clicking
    */
    return new window.ClipboardJS('.debug .t_string, .debug .t_int, .debug .t_float, .debug .t_key', {
      target: function (trigger) {
        var range;
        if ($(trigger).is('a')) {
          return $('<div>')[0]
        }
        if (window.getSelection().toString().length) {
          // text was being selected vs a click
          range = window.getSelection().getRangeAt(0);
          setTimeout(function () {
            // re-select
            window.getSelection().addRange(range);
          });
          return $('<div>')[0]
        }
        notify('Copied to clipboard');
        return trigger
      }
    })
  }

  function registerListeners ($root) {
    if (listenersRegistered) {
      return
    }
    $('body').on('animationend', '.debug-noti', function () {
      $(this).removeClass('animate').closest('.debug-noti-wrap').hide();
    });
    $('.debug').on('mousedown', '.debug a', function () {
      var beforeunload = window.onbeforeunload;
      window.onbeforeunload = null;
      window.setTimeout(function () {
        window.onbeforeunload = beforeunload;
      }, 500);
    });
    listenersRegistered = true;
  }

  function notify (html) {
    $('.debug-noti').html(html).addClass('animate').closest('.debug-noti-wrap').show();
  }

}(window.jQuery));
