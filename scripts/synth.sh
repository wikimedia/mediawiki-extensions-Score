#!/bin/sh

# Get parameters from environment

export SCORE_FLUIDSYNTH="${SCORE_FLUIDSYNTH:-fluidsynth}"
export SCORE_SOUNDFONT="${SCORE_SOUNDFONT:-/usr/share/sounds/sf2/FluidR3_GM.sf2}"
export SCORE_LAME="${SCORE_LAME:-lame}"
export SCORE_PHP="${SCORE_PHP:-php}"

errorExit() {
	printf 'mw-msg:'
	for arg in "$@"; do
		printf '\t%s' "$arg"
	done
	printf '\n'
	exit 1
}

runFluidSynth() {
	if [ ! -e "$SCORE_SOUNDFONT" ]; then
		errorExit score-soundfontnotexists "$SCORE_SOUNDFONT"
	fi
	if [ ! -x "$SCORE_FLUIDSYNTH" ]; then
		errorExit score-fallbacknotexecutable "$SCORE_FLUIDSYNTH"
	fi
	"$SCORE_FLUIDSYNTH" \
		-T wav \
		-F file.wav \
		-r 44100 \
		"$SCORE_SOUNDFONT" \
		file.midi

	status="$?"
	if [ "$status" -ne 0 ]; then
		errorExit score-audioconversionerr
	fi
}

getWavDuration() {
	# Write duration to stdout
	"$SCORE_PHP" scripts/getWavDuration.php file.wav
	status="$?"
	if [ "$status" -ne 0 ]; then
		errorExit score-scripterr getWavDuration.php "$status"
	fi
}

runLame() {
	if [ ! -x "$SCORE_LAME" ]; then
		errorExit score-lamenotexecutable "$SCORE_LAME"
	fi
	"$SCORE_LAME" file.wav file.mp3
	status="$?"
	if [ "$status" -ne 0 ]; then
		errorExit score-audioconversionerr
	fi
}

runFluidSynth
getWavDuration
runLame
