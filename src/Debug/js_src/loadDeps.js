export default function loadDeps(deps) {
	var checkInterval,
		intervalCounter = 1;
	deps.reverse();
	if (document.getElementsByTagName("body")[0].childElementCount == 1) {
		// output only contains debug
		// don't wait for interval to begin
		loadDepsDoer(deps);
	} else {
		loadDepsDoer(deps, true);
	}
	checkInterval = setInterval(function() {
		loadDepsDoer(deps, intervalCounter === 10);
		if (deps.length === 0) {
			clearInterval(checkInterval);
		} else if (intervalCounter === 20) {
			clearInterval(checkInterval);
		}
		intervalCounter++;
	}, 500);
}

function addScript(src) {
	var firstScript = document.getElementsByTagName("script")[0],
		jsNode = document.createElement("script");
	jsNode.src = src;
	firstScript.parentNode.insertBefore(jsNode, firstScript);
}

function addStylesheet(src) {
	var link = document.createElement("link");
	link.type = "text/css";
	link.rel = "stylesheet";
	link.href = src;
	document.head.appendChild(link);
}

function loadDepsDoer(deps, checkOnly) {
	var dep,
		type,
		i;
	for (i = deps.length - 1; i >= 0; i--) {
		dep = deps[i];
		if (dep.check()) {
			// dependency exists
			if (dep.onLoaded) {
				dep.onLoaded();
			}
			deps.splice(i, 1);	// remove it
			continue;
		}
		if (dep.status != "loading" && !checkOnly) {
			dep.status = "loading";
			type = dep.type || 'script';
			if (type == 'script') {
				addScript(dep.src);
			} else if (type == 'stylesheet') {
				addStylesheet(dep.src);
			}
		}
	}
}
