#!/usr/bin/env python3
"""
Katogo video streaming optimizer.

Two modes:
  faststart : lossless remux — moves moov atom to front so playback starts
              immediately. Very fast (~seconds per file), no quality change.
  hls       : full multi-bitrate HLS transcode (360p/480p/720p) for adaptive
              streaming. Slow (re-encodes), run as a background batch.

Usage:
  python3 optimize_videos.py faststart /path/to/movies_dir
  python3 optimize_videos.py hls /path/to/movie.mp4 /path/to/hls_output_root
  python3 optimize_videos.py hls-batch /path/to/movies_dir /path/to/hls_output_root

Requires: ffmpeg + ffprobe installed (apt install ffmpeg).
Output for hls mode:
  {output_root}/{movie_name}/master.m3u8   <- give THIS url to the Flutter app
"""

import json
import subprocess
import sys
import shutil
from pathlib import Path

# Bitrate ladder tuned for East-African mobile networks:
# (name, height, video_bitrate, audio_bitrate)
LADDER = [
    ("360p", 360, "600k", "64k"),
    ("480p", 480, "1000k", "96k"),
    ("720p", 720, "2200k", "128k"),
]
SEGMENT_SECONDS = 6
VIDEO_EXTS = {".mp4", ".mkv", ".avi", ".mov", ".m4v"}


def run(cmd):
    print("  $", " ".join(str(c) for c in cmd))
    subprocess.run(cmd, check=True)


def probe(path):
    out = subprocess.check_output([
        "ffprobe", "-v", "quiet", "-print_format", "json",
        "-show_streams", "-show_format", str(path)
    ])
    return json.loads(out)


def needs_faststart(path):
    """True if moov atom comes after mdat (slow startup over HTTP)."""
    with open(path, "rb") as f:
        head = f.read(64 * 1024)
    moov = head.find(b"moov")
    mdat = head.find(b"mdat")
    if moov != -1 and (mdat == -1 or moov < mdat):
        return False  # moov already at front
    return True


def faststart_file(path):
    path = Path(path)
    if path.suffix.lower() != ".mp4":
        return
    if not needs_faststart(path):
        print(f"OK (already faststart): {path.name}")
        return
    tmp = path.with_suffix(".faststart.tmp.mp4")
    print(f"Remuxing: {path.name}")
    run(["ffmpeg", "-y", "-i", str(path), "-c", "copy",
         "-movflags", "+faststart", str(tmp)])
    tmp.replace(path)
    print(f"DONE: {path.name}")


def faststart_dir(directory):
    for p in sorted(Path(directory).rglob("*.mp4")):
        try:
            faststart_file(p)
        except subprocess.CalledProcessError as e:
            print(f"FAILED: {p} ({e})")


def transcode_hls(src, output_root):
    src = Path(src)
    out_dir = Path(output_root) / src.stem
    if (out_dir / "master.m3u8").exists():
        print(f"SKIP (exists): {out_dir}")
        return
    out_dir.mkdir(parents=True, exist_ok=True)

    info = probe(src)
    v = next(s for s in info["streams"] if s["codec_type"] == "video")
    src_height = int(v.get("height", 720))

    # Don't upscale: only ladder rungs <= source height (keep at least one)
    rungs = [r for r in LADDER if r[1] <= src_height] or [LADDER[0]]

    cmd = ["ffmpeg", "-y", "-i", str(src)]
    var_map = []
    for i, (name, h, vb, ab) in enumerate(rungs):
        cmd += [
            "-map", "0:v:0", "-map", "0:a:0?",
            f"-filter:v:{i}", f"scale=-2:{h}",
            f"-c:v:{i}", "libx264", f"-b:v:{i}", vb,
            f"-maxrate:v:{i}", vb, f"-bufsize:v:{i}", str(int(vb[:-1]) * 2) + "k",
            f"-preset", "veryfast", f"-profile:v:{i}", "main",
            "-g", "48", "-keyint_min", "48", "-sc_threshold", "0",
            f"-c:a:{i}", "aac", f"-b:a:{i}", ab, "-ac", "2",
        ]
        var_map.append(f"v:{i},a:{i},name:{name}")

    cmd += [
        "-f", "hls",
        "-hls_time", str(SEGMENT_SECONDS),
        "-hls_playlist_type", "vod",
        "-hls_segment_type", "mpegts",
        "-hls_flags", "independent_segments",
        "-master_pl_name", "master.m3u8",
        "-var_stream_map", " ".join(var_map),
        "-hls_segment_filename", str(out_dir / "%v" / "seg_%04d.ts"),
        str(out_dir / "%v" / "index.m3u8"),
    ]
    for name, *_ in rungs:
        (out_dir / name).mkdir(exist_ok=True)

    print(f"Transcoding {src.name} -> {out_dir} ({len(rungs)} qualities)")
    run(cmd)
    print(f"DONE. Stream URL playlist: {out_dir / 'master.m3u8'}")


def hls_batch(directory, output_root):
    files = [p for p in sorted(Path(directory).rglob("*"))
             if p.suffix.lower() in VIDEO_EXTS]
    print(f"{len(files)} videos found")
    for p in files:
        try:
            transcode_hls(p, output_root)
        except subprocess.CalledProcessError as e:
            print(f"FAILED: {p} ({e})")


if __name__ == "__main__":
    if not shutil.which("ffmpeg"):
        sys.exit("ffmpeg not found. Install with: apt install ffmpeg")
    args = sys.argv[1:]
    if not args:
        sys.exit(__doc__)
    mode = args[0]
    if mode == "faststart":
        target = Path(args[1])
        faststart_dir(target) if target.is_dir() else faststart_file(target)
    elif mode == "hls":
        transcode_hls(args[1], args[2])
    elif mode == "hls-batch":
        hls_batch(args[1], args[2])
    else:
        sys.exit(__doc__)
