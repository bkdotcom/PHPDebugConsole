export function cookieGet(name) {
	var nameEQ = name + "=",
		ca = document.cookie.split(";"),
		c = null,
		i = 0;
	for ( i = 0; i < ca.length; i += 1 ) {
		c = ca[i];
		while (c.charAt(0) === " ") {
			c = c.substring(1, c.length);
		}
		if (c.indexOf(nameEQ) === 0) {
			return c.substring(nameEQ.length, c.length);
		}
	}
	return null;
}

export function cookieRemove(name) {
	cookieSet(name, "", -1);
}

export function cookieSet(name, value, days) {
	// console.log("cookieSet", name, value, days);
	var expires = "",
		date = new Date();
	if ( days ) {
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		expires = "; expires=" + date.toGMTString();
	}
	document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
}

export function lsGet(key) {
	var path = key.split(".", 2);
    var val = window.localStorage.getItem(path[0]);
    if (typeof val !== "string" || val.length < 1) {
        return null;
    } else {
        try {
            val = JSON.parse(val);
        } catch (e) {
        }
    }
	return path.length > 1
		? val[path[1]]
		: val;
}

export function lsSet(key, val) {
	var path = key.split(".", 2);
	var lsVal;
	key = path[0];
	if (path.length > 1) {
		lsVal = lsGet(key) || {};
		lsVal[path[1]] = val;
		val = lsVal;
	}
    if (val === null) {
        localStorage.removeItem(key);
        return;
    }
    if (typeof val !== "string") {
        val = JSON.stringify(val);
    }
	window.localStorage.setItem(key, val);
}

export function queryDecode(qs) {
	var params = {},
		tokens,
		re = /[?&]?([^&=]+)=?([^&]*)/g;
	if (qs === undefined) {
		qs = document.location.search;
	}
	qs = qs.split("+").join(" ");	// replace + with " "
	while (true) {
		tokens = re.exec(qs);
		if (!tokens) {
			break;
		}
		params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
	}
	return params;
}
