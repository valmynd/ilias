// notes on how cookies would have to be done in PHP (not neccessary, as it can be done in javascript, entirelly):
//calling header('Set-Cookie: ...') would prevent cookies previously supplied with setcookie('...'); from being sent!
//source: http://stackoverflow.com/questions/5499476/is-it-ok-to-send-cookie-headers-directly-with-header-calls

/**
 * @param {String} str Path, e.g. /x/y/z.ogv (filename not contain whitespaces or semicolons!)
 * @returns {String} e.g. z.ogv
 */
function extractFilenameFromPath(str) {
	return str.replace(/^.*[\\\/]/, '')
}

/**
 * @return {Boolean}
 */
function canPlay() {
	var cookies = document.cookie.split(';');
	for (var i = 0; i < cookies.length; i++) {
		var cookie = cookies[i].split('=');
		if (cookie.length !== 2) continue;
		// get key and value from each cookie, stripping whitespaces for each file
		var key = cookie[0].replace(/\s+/g, ''),
			value = cookie[1].replace(/\s+/g, '');
		if (value === "playedbefore" || true) {
			// check if there is a "src" attrubute of a "source" element ends with the filename from the cookie
			var result = $('source[src$="' + key + '"]');
			if (result.length > 0) {
				// this means something with this filename was found which means file has been played already
				return false;
			}
		}
	}
	return true;
}

$(document).ready(function () {
	if (canPlay()) {
		$('audio,video').mediaelementplayer({
			features: ['playpause', 'progress', 'current', 'duration', 'volume'], // later, remove 'progress' from this list (currently nice to have it for testing)
			enableKeyboard: false,
			// method that fires when the Flash or Silverlight object is ready
			success: function (mediaElement, domObject) {
				mediaElement.addEventListener('ended', function (event) {
					// create session cookie to prevent replay of any source file in the html document
					$('source').each(function (index) {
						var srcpath = $(this).attr("src"),
							filename = extractFilenameFromPath(srcpath);
						// a cookie set up like this will expire at the end of session (usually when the browser is closed)
						// read http://stackoverflow.com/questions/6791944/how-exactly-does-document-cookie-work
						document.cookie = filename + "=" + "playedbefore"
					});
				}, false);
				// autoplay
				mediaElement.play();
			}
		});
	} else {
		$('audio,video').replaceWith("This file was played already (Remove Cookies to play again).");

	}
});
