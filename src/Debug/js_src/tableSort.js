import $ from "jquery";

/**
 * Add sortability to given table
 */
export function makeSortable(table) {

	var	$table = $(table),
		$head = $table.find("> thead");
	if (!$table.is("table.sortable")) {
		return $table;
	}
	$table.addClass("table-sort");
	$head.on("click", "th", function() {
		var $th = $(this),
			$cells = $(this).closest("tr").children(),
			i = $cells.index($th),
			curDir = $th.is(".sort-asc") ? "asc" : "desc",
			newDir = curDir === "desc" ? "asc" : "desc";
		$cells.removeClass("sort-asc sort-desc");
		$th.addClass("sort-"+newDir);
		if (!$th.find(".sort-arrows").length) {
			// this th needs the arrow markup
			$cells.find(".sort-arrows").remove();
			$th.append('<span class="fa fa-stack sort-arrows pull-right">' +
					'<i class="fa fa-caret-up" aria-hidden="true"></i>' +
					'<i class="fa fa-caret-down" aria-hidden="true"></i>' +
				"</span>");
		}
		sortTable($table[0], i, newDir);
	});
	return $table;
}

/**
 * Sort table
 *
 * @param obj table dom element
 * @param int col   column index
 * @param str dir   (asc) or desc
 */
function sortTable(table, col, dir) {
	var body = table.tBodies[0],
		rows = body.rows,
		i,
		floatRe = /^([+\-]?(?:0|[1-9]\d*)(?:\.\d*)?)(?:[eE]([+\-]?\d+))?$/,
		collator = typeof Intl.Collator === "function"
			? new Intl.Collator([], {
				numeric: true,
				sensitivity: "base"
			})
			: false;
	dir = dir === "desc" ? -1 : 1;
	rows = Array.prototype.slice.call(rows, 0), // Converts HTMLCollection to Array
	rows = rows.sort(function (trA, trB) {
		var a = trA.cells[col].textContent.trim(),
			b = trB.cells[col].textContent.trim(),
			afloat = a.match(floatRe),
			bfloat = b.match(floatRe);
		if (afloat && afloat[2]) {
			// sci notation
			a = Number.parseFloat(a).toFixed(6);
		}
		if (bfloat && bfloat[2]) {
			// sci notation
			b = Number.parseFloat(b).toFixed(6);
		}
		if (afloat && bfloat) {
			if (a < b) {
				return -1;
			}
			if (a > b) {
				return 1;
			}
			return 0;
		}
		return collator
			? dir * collator.compare(a, b)
			: dir * a.localeCompare(b);	// not a natural sort
	});
	for (i = 0; i < rows.length; ++i) {
		body.appendChild(rows[i]); // append each row in order (which moves)
	}
}
