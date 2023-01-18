/* LOL HEYURI
 */
function insertThisInThere(thisChar, thereId) {
	function theCursorPosition(ofThisInput) {
		// set a fallback cursor location
		var theCursorLocation = 0;
 
		// find the cursor location via IE method...
		if (document.selection) {
			ofThisInput.focus();
			var theSelectionRange = document.selection.createRange();
			theSelectionRange.moveStart('character', -ofThisInput.value.length);
			theCursorLocation = theSelectionRange.text.length;
		} else if (ofThisInput.selectionStart || ofThisInput.selectionStart == '0') {
			// or the FF way 
			theCursorLocation = ofThisInput.selectionStart;
		}
		return theCursorLocation;
	}
 
	// now get ready to place our new character(s)...
	var theIdElement = document.getElementById(thereId);
	var currentPos = theCursorPosition(theIdElement);
	var origValue = theIdElement.value;
	var newValue = origValue.substr(0, currentPos) + thisChar + origValue.substr(currentPos);
 
	theIdElement.value = newValue;
 
}
        function toggle_aa_mode() {
            var app = document.getElementById('com');
            if (localStorage.lightMode == "aa1") {
                localStorage.lightMode = "aa2";
                app.setAttribute("light-mode", "aa2");
            } else {
                localStorage.lightMode = "aa1";
                app.setAttribute("light-mode", "aa1");
            }       
        }

		window.addEventListener("storage", function () {
			if (localStorage.lightMode == "aa1") {
				app.setAttribute("light-mode", "aa1");
			} else {
				app.setAttribute("light-mode", "aa2");
			}
		}, false);