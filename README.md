# Youtube playlist video files' names sorter.
PHP script for sorting video files (from playlist), downloaded from youtube by adding zero-leading numbers to file names.

1. Edit the file config.php and set the PATH constant to the absolute path of the directory containing the downloaded video files (from playlist).
2. Run the index.bat (on Windows)

If audio and video files are not merged yet, you can just run merge.bat and it will merge audio and video files.
To use this function, you must have ffmpeg.
You must edit config.php and set ffmpeg absolute path to the constant FFMPEG_PATH.
