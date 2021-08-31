Score, a MediaWiki extension for rendering musical scores with LilyPond.

Security
========

By default, this extension runs LilyPond in safe mode, however there are known
unfixed safe mode escape vulnerabilities leading to arbitrary execution. So it
is recommended that LilyPond is run as an unprivileged user inside an isolated
container with no external network access.

MediaWiki's system for remote execution of shell commands is called Shellbox.
Instructions to set up a Shellbox server can be found at:
<https://www.mediawiki.org/wiki/Shellbox>.


Ensure the following additional packages are installed in your Shellbox container:

* LilyPond
* Ghostscript
* ImageMagick
* FluidSynth
* A SoundFont for FluidSynth, for example Fluid (R3) General MIDI SoundFont (GM)
* LAME

You'll also want fonts for whatever characters are used in lyrics. The Noto
font family may be a good choice as it aims to cover all scripts.

Running on Windows
==================

Running the Score extension on Windows is possible, although inadvisable.

Shellbox cannot do cross-platform requests, so if MediaWiki runs on Windows, it
would be necessary to run LilyPond on Windows as well. Proper security isolation
in such a setup would be difficult. Consider instead running MediaWiki inside a
Linux container.

Score uses POSIX shell scripts and so requires a Bash or a similar shell to
be installed, for example, the MinGW shell that is distributed with **Git for Windows**.
Configure its location with `$wgScoreShell`, typically:

```
$wgScoreShell = 'C:\Program Files\Git\bin\bash.exe';
```

GhostScript is distributed with LilyPond, however this GhostScript cannot find
its library directory unless an environment variable is set, e.g.:

```
$wgScoreEnvironment = [
	'GS_LIB' => 'C:\Program Files (x86)\LilyPond\usr\share\ghostscript\9.21\Resource\Init'
];
```

Extension setup
===============

1. Change to the "extensions" directory of your MediaWiki installation.
2. Clone this repository.
3. Create a subdirectory named "lilypond" in your $wgUploadDirectory (usually
   the directory named "images" in in your MediaWiki directory). Make sure
   the directory is writable by your webserver. If you do not create this
   directory, the Score extension will attempt to create it for you with the
   rights available to it.
4. Add the lines
```
   wfLoadExtension( 'score' );
   $wgScoreTrim = true;
   $wgImageMagickConvertCommand = '/usr/bin/convert';
   $wgShellboxUrl = 'http://shellbox.internal/shellbox';
   $wgShellboxSecretKey = '... your secret key ...';
```
   to your LocalSettings.php file.


If you get unexpected out-of-memory errors, you may also have to increase
[$wgMaxShellMemory](https://www.mediawiki.org/wiki/Manual:$wgMaxShellMemory).

By default, Score will look for binaries by their usual names in /usr/bin. If
you need to customise the locations of any of the binaries, you can copy the
lines below and change them as necessary:

```
$wgScoreLilyPond = '/usr/bin/lilypond';
$wgScoreAbc2Ly = '/usr/bin/abc2ly'; /* part of LilyPond */
$wgScoreFluidsynth = '/usr/bin/fluidsynth';
$wgScoreSoundfont = '/usr/share/sounds/sf2/FluidR3_GM.sf2'; /* for FluidSynth */
$wgScoreGhostScript = '/usr/bin/gs';
```

Usage
=====

After setup, you can use the <score>…</score> tags in your wiki markup.
For a simple score, use e.g.

```
<score>\relative c' { f d f a d f e d cis a cis e a g f e }</score>
```

This will render the appropriate score as a PNG image.

You may also specify attributes to the score tags in the general form

```
<score attribute1="value1" attribute2="value2">…</score>.
```

The following attributes are available:

* Attribute: lang
  - Allowed values: ABC, lilypond (default)
  - Effect: Sets the score language. For example, to provide a score in ABC
          notation, you might use

          <score lang="ABC">
          X:1
          M:C
          L:1/4
          K:C
          C, D, E, F,|G, A, B, C|D E F G|A B c d|
          e f g a|b c' d' e'|f' g' a' b'|]
          </score>.

* Attribute: override_midi
  - Allowed values: Known file name, that is, if override_midi="name" is given,
                  [[File:name]] is not a redlink.
  - Effect: Embeds the score image(s) into a hyperlink to a specified MIDI file.
          This is an alternative to the midi attribute (see above). It can, for
          example, be useful if you have a suitable MIDI file of superior
          quality compared with the auto-generated MIDI file the midi attribute
          yields. Of course, you can still omit both attributes in this case and
          add the file manually to the page, if you prefer.

* Attribute: override_audio
  - Alias: override_ogg
  - Allowed values: Known file name, that is, if override_audio="name" is given,
                  [[File:name]] is not a redlink.
  - Effect: Embeds the media specified by the file name in the HTML below the
          score image(s). This is an alternative to the vorbis attribute (see
          below). Its use is similar to the use of the override_midi attribute
          (see above).

* Attribute: raw
  - Effect: If included in the tag, the score code is interpreted as a complete
          LilyPond file. Use this option if you want to create more complex
          scores. If the score language (lang attribute) is not set to
          lilypond, this attribute is ignored.

* Attribute: sound
  - Alias: vorbis
  - Effect: If included in the tag, an audio file will be generated for the
          score. An audio player will be embedded in the HTML below
          the score image(s).
