// notes on how cookies would have to be done in PHP (not neccessary, as it can be done in javascript, entirelly):
//calling header('Set-Cookie: ...') would prevent cookies previously supplied with setcookie('...'); from being sent!
//source: http://stackoverflow.com/questions/5499476/is-it-ok-to-send-cookie-headers-directly-with-header-calls

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
			// check if there is a "src" attrubute of a "source" element with the filename from the cookie
			var filename = decodeURIComponent(key);
			var result = $('source[src="' + filename + '"]');
			if (result.length > 0) {
				// this means something with this filename was found which means file has been played already
				return false;
			}
		}
	}
	return true;
}

function removePlayer() {
	$('audio,video').replaceWith("This file was played already (Remove Cookies to play again).");
	$('.mejs-container').replaceWith("This file was played already (Remove Cookies to play again).");
}

$(document).ready(function () {
	//console.log(document.cookie)
	if (canPlay()) {
		$('audio,video').mediaelementplayer({
			features: ['current', 'duration', 'volume'], // for development, add 'playpause', 'progress',
			enableKeyboard: false,
			// method that fires when the Flash or Silverlight object is ready
			success: function (mediaElement, domObject) {
				mediaElement.addEventListener('ended', function (event) {
					// create session cookie to prevent replay of any source file in the html document
					$('source').each(function (index) {
						var filename = $(this).attr("src"),
							cookiekey = encodeURIComponent(filename);
						// a cookie set up like this will expire at the end of session (usually when the browser is closed)
						// read http://stackoverflow.com/questions/6791944/how-exactly-does-document-cookie-work
						document.cookie = cookiekey + "=" + "playedbefore"
						// remove player
						removePlayer();
					});
				}, false);
				// autoplay
				mediaElement.play();
			}
		});
	} else {
		removePlayer()
	}
});
