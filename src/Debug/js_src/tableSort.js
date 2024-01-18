import $ from 'jquery'

/**
 * Add sortability to given table
 */
export function makeSortable (table) {
  var $table = $(table)
  var $head = $table.find('> thead')
  if (!$table.is('table.sortable')) {
    return $table
  }
  $table.addClass('table-sort')
  $head.on('click', 'th', function () {
    var $th = $(this)
    var $cells = $(this).closest('tr').children()
    var i = $cells.index($th)
    var curDir = $th.is('.sort-asc') ? 'asc' : 'desc'
    var newDir = curDir === 'desc' ? 'asc' : 'desc'
    $cells.removeClass('sort-asc sort-desc')
    $th.addClass('sort-' + newDir)
    if (!$th.find('.sort-arrows').length) {
      // this th needs the arrow markup
      $cells.find('.sort-arrows').remove()
      $th.append('<span class="fa fa-stack sort-arrows float-right">' +
          '<i class="fa fa-caret-up" aria-hidden="true"></i>' +
          '<i class="fa fa-caret-down" aria-hidden="true"></i>' +
        '</span>')
    }
    sortTable($table[0], i, newDir)
  })
  return $table
}

/**
 * Sort table
 *
 * @param obj table    dom element
 * @param int colIndex column index
 * @param str dir      (asc) or desc
 */
function sortTable (table, colIndex, dir) {
  var body = table.tBodies[0]
  var rows = body.rows
  var i
  var collator = typeof Intl.Collator === 'function'
    ? new Intl.Collator([], {
      numeric: true,
      sensitivity: 'base'
    })
    : null
  dir = dir === 'desc' ? -1 : 1
  rows = Array.prototype.slice.call(rows, 0) // Converts HTMLCollection to Array
  rows = rows.sort(rowComparator(colIndex, dir, collator))
  for (i = 0; i < rows.length; ++i) {
    body.appendChild(rows[i]) // append each row in order (which moves)
  }
}

function rowComparator (colIndex, dir, collator) {
  var floatRe = /^([+-]?(?:0|[1-9]\d*)(?:\.\d*)?)(?:[eE]([+-]?\d+))?$/
  return function sortFunction (trA, trB) {
    var a = trA.cells[colIndex].textContent.trim()
    var b = trB.cells[colIndex].textContent.trim()
    var afloat = a.match(floatRe)
    var bfloat = b.match(floatRe)
    if (afloat) {
      a = toFixed(a, afloat)
    }
    if (bfloat) {
      b = toFixed(b, bfloat)
    }
    return dir * compare(a, b, collator)
  }
}

function compare (a, b, collator) {
  var comp = 0
  if (afloat && bfloat) {
    if (a < b) {
      comp = -1
    } else if (a > b) {
      comp = 1
    }
    return comp
  }
  return collator
    ? collator.compare(a, b)
    : a.localeCompare(b) // not a natural sort
}

function toFixed (str, matches) {
  var num = Number.parseFloat(str)
  if (matches[2]) {
    // sci notation
    num = num.toFixed(6)
  }
  return num
}
