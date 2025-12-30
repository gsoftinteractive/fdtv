# Hero Background Video

## Instructions

To add a video background to the landing page hero section, place your video file here with the name `hero-bg.mp4`.

## Free Video Resources

You can download free, high-quality videos from these sources:

### Recommended Sites:
1. **Pixabay** (https://pixabay.com/videos/)
   - Search for: "news broadcasting", "tv studio", "newsroom", "media center", "broadcast"
   - License: Free for commercial use, no attribution required

2. **Pexels** (https://www.pexels.com/videos/)
   - Search for: "news anchor", "television studio", "broadcasting", "media production"
   - License: Free for commercial and non-commercial use

3. **Videvo** (https://www.videvo.net/)
   - Search for: "news graphics", "broadcast backgrounds", "tv station"
   - License: Many free options available (check individual licenses)

### Recommended Search Terms:
- "news broadcasting background"
- "television studio"
- "newsroom background"
- "media broadcast"
- "news ticker background"
- "tv station loop"

### Video Specifications:
- **Format**: MP4 (H.264 codec recommended)
- **Resolution**: 1920x1080 (Full HD) or higher
- **Duration**: 10-30 seconds (will loop automatically)
- **Size**: Aim for under 10MB for faster loading
- **Type**: Subtle background videos work best (news graphics, abstract tech, city skylines, news studio)

### Tips:
- Choose videos with subtle movement to avoid distracting from content
- Darker videos work better as they allow text to remain readable
- The video will be automatically blurred and dimmed for better text readability
- Loop-able videos provide the best seamless experience

## Example Commands to Convert/Optimize Video:

If you have ffmpeg installed, you can optimize your video:

```bash
# Reduce file size while maintaining quality
ffmpeg -i input.mp4 -c:v libx264 -crf 28 -preset slow -vf scale=1920:-2 hero-bg.mp4

# Create a short loop (first 15 seconds)
ffmpeg -i input.mp4 -t 15 -c:v libx264 -crf 28 hero-bg.mp4
```

## Fallback

If no video file is present, the page will gracefully fall back to the animated gradient background that was originally in place.
