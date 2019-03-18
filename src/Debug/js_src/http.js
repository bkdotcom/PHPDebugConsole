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
	cookieSave(name, "", -1);
}

export function cookieSave(name, value, days) {
	console.log("cookieSave", name, value, days);
	var expires = "",
		date = new Date();
	if ( days ) {
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		expires = "; expires=" + date.toGMTString();
	}
	document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
}

export function lsGet(key) {
	return JSON.parse(window.localStorage.getItem(key));
}

export function lsSet(key, val) {
	window.localStorage.setItem(key, JSON.stringify(val));
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
