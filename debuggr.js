// shortcut for updating the status text
function statusMessage(msg) {
	document.getElementById("statusMsg").innerHTML = msg;
}

// toggle the row numbers using CSS
function toggleNums() {
	closeMenus();
	if (lineNumbersOn) {
		document.body.classList.remove('linesOn');
		document.querySelector("#optLineNumbers span").innerHTML = "&nbsp;";
	} else {
		document.body.classList.add('linesOn');
		document.querySelector("#optLineNumbers span").innerHTML = "&check;";
	}
	lineNumbersOn = !lineNumbersOn;
}

// toggle the column numbers using CSS
function toggleCols() {
	closeMenus();
	if (colNumbersOn) {
		document.getElementById("codeLines").classList.add("colsOff");
		document.querySelector("#optColumns span").innerHTML = "&nbsp;";
	} else {
		document.getElementById("codeLines").classList.remove("colsOff");
		document.querySelector("#optColumns span").innerHTML = "&check;";
	}
	colNumbersOn = !colNumbersOn;
}

// toggle visual dark/lite mode
function toggleVisualMode() {
	closeMenus();
	if (darkModeOn) {
		document.body.classList.remove("darkMode");
		document.querySelector("#optDarkMode span").innerHTML = "&nbsp;";
		document.querySelector("style").innerHTML += "::-webkit-scrollbar-track { background: #aaa; } ::-webkit-scrollbar-thumb { background: #333; }";
	} else {
		document.body.classList.add("darkMode");
		document.querySelector("#optDarkMode span").innerHTML = "&check;";
		document.querySelector("style").innerHTML += "::-webkit-scrollbar-track { background: #333; } ::-webkit-scrollbar-thumb { background: #aaa; }";
	}
	darkModeOn = !darkModeOn;
}

// toggle the auto-load pulse check every 5 seconds
function toggleReloadTimer() {
	closeMenus();
	if (!reloadTimer) {
		reloadPulse = setInterval(checkPulse, 5000);
		document.querySelector("#optReload span").innerHTML = "&check;";
		checkPulse();
	} else {
		clearInterval(reloadPulse);
		document.querySelector("#optReload span").innerHTML = "&nbsp;";
	}
	reloadTimer = !reloadTimer;
}

// select the code text 
function selectCode() {
	var r = document.createRange();
	var w = document.querySelector("#codeLines pre");  
	r.selectNodeContents(w);  
	var sel = window.getSelection(); 
	sel.removeAllRanges(); 
	sel.addRange(r);
}

// update the #fileNav UL-based menu to open and close with clicks
function setMenuClicks() {
	allLI = document.querySelectorAll("#fileNav li");
	for (x=0; x<allLI.length; x++) {
		allLI[x].onclick = function(event) {
			document.querySelector('#optionsNav li').classList.remove('showSub');
			this.classList.toggle("showSub");
			event.stopPropagation();
		}
	}
}

// use AJAX to check pulse on the file; has it been updated since last load?
function checkPulse(toCloseMenus) {
	if (toCloseMenus) closeMenus();
	statusMessage("&bull;");
	ajax = new XMLHttpRequest();
	ajax.onreadystatechange = function() {
			if ((this.readyState == 4) && (this.status == 200)) {
				switch (this.responseText) {
					case "0": logout(); break;
					case "1": loadFile(); break;
					case "2": loadMenu(); break;
					case "3": loadFile(); loadMenu(); break;
					default: statusMessage("");								
				}
			} else if ((this.readyState == 4) && (this.status != 200)) {
				console.log("AJAX pulse error: " + this.responseText);
				statusMessage("!");
			}
	}
	urlToLoad = urlToSelf + "?mode=pulse";
	ajax.open("GET", urlToLoad, true);
	ajax.send();
}

function loadMenu() {
	statusMessage("&#8857;");
	ajax = new XMLHttpRequest();
	ajax.onreadystatechange = function() {
			if ((this.readyState == 4) && (this.status == 200)) {
				document.getElementById("fileNav").outerHTML = this.responseText;
				setMenuClicks();
				statusMessage("");
			} else if ((this.readyState == 4) && (this.status != 200)) {
				console.log("AJAX menu error: " + this.responseText);
				statusMessage("!");
			}
	}
	urlToLoad = urlToSelf + "?mode=menu";
	ajax.open("POST", urlToLoad, true);
	ajax.send();
}

// use AJAX to reload the file or to load files from the Files menu (if enabled)
function loadFile(fileToLoad = baseFile, historyUpdate = true, toTidy = false) {
	document.body.classList.add("isLoading");
	closeMenus();
	codeLinesPre = document.querySelector("#codeLines pre");
	statusMessage("&#8857;");
	ajax = new XMLHttpRequest();
	ajax.onreadystatechange = function() {
		if ((this.readyState == 4) && (this.status == 200)) {
			if (fileToLoad != baseFile) {
				document.getElementById("codeNums").scrollTop = 0;
				document.getElementById("codeLines").scrollTop = 0;
			}
			baseFile = fileToLoad;
			codeLinesPre.innerHTML = this.responseText;
			styleCode(fileToLoad);
			prepCodeNumbers();
			document.title = "Debuggr: " + fileToLoad;
			document.querySelector("#filenameRef span").innerHTML = fileToLoad;
			if (historyUpdate) {
				historyURL = urlToSelf + "?file=" + fileToLoad;
				window.history.pushState( {}, "", historyURL);
			}
			statusMessage("");
			document.body.classList.remove("isLoading");
		} else if ((this.readyState == 4) && (this.status != 200)) {
			codeLinesPre.innerHTML = noFileMessage;
			console.log("Error: " + this.responseText);
			document.body.classList.remove("isLoading");
			statusMessage("!");
		}
	}
	if (toTidy) addtidy = "&maketidy=true";
	else addtidy = "";
	urlToLoad = urlToSelf + "?mode=ajax" + addtidy + "&file=" + encodeURIComponent(fileToLoad);
	console.log(urlToLoad);
	ajax.open("POST", urlToLoad, true);
	ajax.send();
}

// test if string is a valid URL; unused but saving for future use
function isValidUrl(u) {
	try { test = new URL(u); }
	catch (err) {	return false; }
	return true;
}

// output line and column numbers in #codeNums pre, padding numbers with 0s to appropriate width; calculate and add column numbers
function prepCodeNumbers() {
	codeCols = document.getElementById("codeCols");
	codeCols.innerHTML = ""; // clear column numbers
	codeNumsPre = document.querySelector("#codeNums pre");
	codeNumsPre.innerHTML = ""; // clear row numbers
	maxWidth = 1; // set column character width low
	numLines = document.querySelector("#codeLines pre").innerHTML.split("\n"); // extract lines into an array
	padTo = numLines.length.toString().length + 1; // number of zeros to add to front of line numbers

	// update the CSS with new widths
	document.querySelector("style").innerHTML += "body.linesOn #codeNums { width: " + (padTo-1) + "rem; } body.linesOn #codeLines { width: calc(100vw - " + (padTo - 0.5) + "rem); left: " + (padTo - 0.5) + "rem; }";
  
	// output line numbers and check line widths for column output
	for (x=1; x<=numLines.length; x++) {
		codeNumsPre.innerHTML += (x + ":").padStart(padTo, "0") + "\n";
		if ((x<numLines.length) && (numLines[x].length > maxWidth)) maxWidth = numLines[x].length;
	}

	// output columns based on maxWidth analysis
	outputColumns = "";
	for (x=1; x<((maxWidth/10)+1); x++) outputColumns += "<span>" + (x * 10) + "</span>";
	codeCols.innerHTML = outputColumns;

	return true;
}

// download a file
function downloadFile() {
	urlToDownload = urlToSelf + "?mode=download&file=" + baseFile;
	window.location.href = urlToDownload;
}

// go to a line
function lineJumper() {
	closeMenus();
	toJump = window.prompt("Go to line number:", "");
	if (!isNaN(toJump)) {
		jumpLine = (toJump-1) * 20.7; // based on CSS of font size and line height
		document.getElementById("codeNums").scrollTop = jumpLine;
		document.getElementById("codeLines").scrollTop = jumpLine;
	}
}

// open a file or URL
function openFile() {
	closeMenus();
	toOpen = window.prompt("Enter a filename" + (allowRemoteFileOn ? " or complete URL" : ""), baseFile);
	if ((toOpen != "") && (toOpen !== null)) {
		document.body.classList.add("isLoading");
		loadFile(toOpen);
	}
}

// close all the menus
function closeMenus() {
	allLI = document.querySelectorAll("#fileNav li");
	for (x=0; x<allLI.length; x++) allLI[x].classList.remove("showSub");
	document.querySelector("#optionsNav li").classList.remove("showSub");
}

// reload and apply PHP Tidy function
function tidyCode() {
	loadFile(baseFile, false, true);
}

// apply Highlights.js and Anchorme.js, if configured in PHP
function styleCode(sPassed) {
	if (highlightCodeOn) {			
		toStyle = document.querySelector('#codeLines pre');
		toStyle.className = ""; // erase pre-existing highlight.js classes applied here

		// make a backup of the original unformatted source
		// doing now for possible future features (TBA)
		sourceBackup = document.getElementById('codeBackup'); // check for element
		if (!sourceBackup) { // is it null?
			sourceBackup = document.createElement('div'); // make a new element
			sourceBackup.id = "codeBackup";
			sourceBackup.style.display = "none"; // make it hidden
			sourceBackupPre = document.createElement('pre');
			sourceBackup.appendChild(sourceBackupPre);
			document.body.appendChild(sourceBackup); // attach it to the end of the DOM
		}
		document.querySelector('#codeBackup pre').innerHTML = toStyle.innerHTML; // copy the innerHTML of the code to the backup

		hljs.highlightElement(toStyle); // run highlight.js on the code block

		// prep hyperlinking
		pageTags = document.querySelectorAll(".hljs-string");
		const noProtocol = new RegExp('^.*.(htm|html|js|css|jpg|png|gif|php|mp3|mp4)$');
		const someProtocol = new RegExp('^(http|https).*$');
		for (const tag of pageTags) {
			sTag = tag.innerHTML.substr(1, (tag.innerHTML.length - 2) );
			if (someProtocol.test(sTag)) {
				tag.innerHTML = tag.innerHTML.substr(0,1) + '<a href="' + urlToSelf + '?file=' + sTag + '">' + sTag + '</a>' + tag.innerHTML.substr(-1);
			} else if (noProtocol.test(sTag)) {
				sTagPre = "";
				if ( sTag.startsWith("//") ) {
					if ( sPassed.startsWith("http://") ) sTagPre = "http:";
					else sTagPre = "https:";
				} else {
					if ( sPassed.search("/") != -1) {
						if ( sPassed.startsWith("/") ) {
							sTagPre = "/";
						}
						aPassed = sPassed.split("/");
						basePassed = aPassed.pop(); 
						if ( sPassed.endsWith("/") ) {
								sTagPre += aPassed.join("/");
						} else {
							sTagPre += aPassed.join("/") + "/";
						}
					}
				}
				tag.innerHTML = tag.innerHTML.substr(0,1) + '<a href="' + urlToSelf + '?file=' + sTagPre + sTag + '">' + sTag + '</a>' + tag.innerHTML.substr(-1);
			}
		}
	} // end if highlightCodeOn
}

// submit the hidden logout form
function logout() { document.getElementById("logoutForm").submit(); }

// when the window loads, prep line numbers, and connect the scrollTops of #codeLines to #codeNums
window.onload = function() {

	// output line and column numbers
	prepCodeNumbers();

	// reposition #codeNums as the user scrolls
	document.getElementById("codeLines").onscroll = function() { 
		document.getElementById("codeNums").scrollTop = document.getElementById("codeLines").scrollTop; 
		document.getElementById("codeCols").style.top = document.getElementById("codeLines").scrollTop + "px"; 
	}

	// reposition #codeCols as the user scrolls
	document.querySelector("#codeLines pre").onscroll = function() {
		document.getElementById("codeCols").style.left = (0 - document.querySelector("#codeLines pre").scrollLeft) + "px"; 
	}			

	// set clicks for the file menu
	setMenuClicks();

	// set clicks for the options menu
	document.querySelector('#optionsNav li').onclick = function() { 
		this.classList.toggle("showSub");
	}

	// close menus when someone clicks on the main code
	document.getElementById("codeLines").onclick = function() { closeMenus(); }
	document.getElementById("codeNums").onclick = function() { closeMenus(); }

	// apply highlight.js
	styleCode(baseFile);

	// since the URL is changed dynamically, we need to dynamically respond to back buttons
	window.onpopstate = function(event) {
		historyParam = document.location.href.split( (basenameFile + "?file=") ).pop(); // get the file value passed to debuggr
		loadFile(historyParam, false);
	};

	// all done loading? remove the isLoading CSS class
	document.body.classList.remove("isLoading");
}		
