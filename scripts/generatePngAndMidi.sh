#!/bin/sh

# Get parameters from environment

export SCORE_ABC2LY="${SCORE_ABC2LY:-abc2ly}"
export SCORE_LILYPOND="${SCORE_LILYPOND:-lilypond}"
export SCORE_SAFE="${SCORE_SAFE:-yes}"
export SCORE_GHOSTSCRIPT="${SCORE_GHOSTSCRIPT:-gs}"
export SCORE_CONVERT="${SCORE_CONVERT:-convert}"
export SCORE_TRIM="${SCORE_TRIM:-no}"
export SCORE_USESVG="${SCORE_USESVG:-no}"
export SCORE_PHP="${SCORE_PHP:-php}"

errorExit() {
	printf 'mw-msg:' 1>&2
	for arg in "$@"; do
		printf '\t%s' "$arg" 1>&2
	done
	printf '\n' 1>&2
	exit 1
}

traceExec() {
  echo + "$*" 1>&2
  "$@"
  status="$?"
}

runPhp() {
	traceExec "$SCORE_PHP" "$@"
	if [ "$status" -ne 0 ]; then
		if [ "$status" -eq 20 ]; then
			# Error already shown
			exit 1
		fi
		errorExit score-scripterr "$1" "$status"
	fi
}

generateLyFromAbc() {
	if [ ! -x "$SCORE_ABC2LY" ]; then
		errorExit score-abc2lynotexecutable "$SCORE_ABC2LY"
	fi
	traceExec "$SCORE_ABC2LY" -s -o file.ly file.abc
	if [ "$status" -ne 0 ]; then
		errorExit score-abcconversionerr
	fi
	runPhp scripts/removeTagline.php file.ly
}

runLilypond() {
	if [ ! -x "$SCORE_LILYPOND" ]; then
		errorExit score-notexecutable "$SCORE_LILYPOND"
	fi
	if [ "$SCORE_SAFE" != no ]; then
		# Safe mode was removed in LilyPond 2.23.12 and causes an error when used.
		mode="-dsafe"
	else
		mode=""
	fi

	# LilyPond with libcairo (since 2.23.82) generates cropped PNG and SVG directly.
	if [ "$SCORE_USESVG" = yes ]; then
		svg="-dno-use-paper-size-for-page -dbackend=cairo --svg --png"
	else
		svg=""
	fi

	# Reduce the GC yield to 25% since testing indicates that this will
	# reduce memory usage by a factor of 3 or so with minimal impact on
	# CPU time. Tested with http://www.mutopiaproject.org/cgibin/piece-info.cgi?id=108
	# Note that if Lilypond is compiled against Guile 2.0+, this
	# probably won't do anything.
	LILYPOND_GC_YIELD=25 \
	traceExec "$SCORE_LILYPOND" \
		-dmidi-extension=midi \
		"$mode" \
		$svg \
		--ps \
		--header=texidoc \
		--loglevel=ERROR \
		file.ly

	if [ "$status" -ne 0 ]; then
		errorExit score-compilererr
	fi
	if [ ! -e file.ps ]; then
		errorExit score-nops
	fi
}

getPageSize() {
	runPhp scripts/extractPostScriptPageSize.php file.ps > dims
	read -r SCORE_WIDTH SCORE_HEIGHT < dims
	export SCORE_WIDTH
	export SCORE_HEIGHT
}

runGhostscript() {
	traceExec "$SCORE_GHOSTSCRIPT" \
		-q \
		-dGraphicsAlphaBits=4 \
		-dTextAlphaBits=4 \
		-dDEVICEWIDTHPOINTS=$SCORE_WIDTH \
		-dDEVICEHEIGHTPOINTS=$SCORE_HEIGHT \
		-dNOPAUSE \
		-dSAFER \
		-sDEVICE=png16m \
		-sOutputFile=file-page%d.png \
		-r101 \
		file.ps \
		-c quit
	if [ "$status" -ne 0 ]; then
		errorExit score-gs-error
	fi
}

trimImages() {
	i=1
	while [ -e "file-page$i.png" ]; do
		traceExec "${SCORE_CONVERT:-convert}" \
				-trim \
				-transparent \
				white \
				"file-page$i.png" \
				"trimmed.png"

		if [ "$status" -ne 0 ]; then
			errorExit score-trimerr
		fi

		mv "trimmed.png" "file-page$i.png"

		i=$(($i + 1))
	done
}

if [ -e file.abc ]; then
	generateLyFromAbc
fi
runLilypond
if [ "$SCORE_USESVG" = no ]; then
	getPageSize
	runGhostscript
	if [ "$SCORE_TRIM" = yes ]; then
		trimImages
	fi
fi
