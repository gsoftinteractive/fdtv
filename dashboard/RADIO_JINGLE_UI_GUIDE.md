# Radio Jingle & Playlist Settings UI Guide

## Backend Implementation: ✅ COMPLETE

The backend code for handling radio jingle timing has been added to `radio.php`.

### What's Already Done:
- Database migration created (`phase6_radio_jingle_timing.sql`)
- Form handling added for `update_playlist_settings` action
- Validation for jingle and advert intervals (1-100)
- Database columns added to stations table

### Database Columns Added:
- `radio_jingle_enabled` - Enable/disable jingles
- `radio_jingle_interval` - Tracks between jingles
- `radio_advert_enabled` - Enable/disable adverts
- `radio_advert_interval` - Tracks between adverts
- `radio_playlist_mode` - Sequential, Shuffle, or Priority

## Frontend UI: TO BE ADDED

Add this HTML form section to `radio.php` after the main radio settings form:

```html
<!-- Playlist & Jingle Settings Card -->
<div class="card">
    <div class="card-header">
        <h3>📻 Playlist & Jingle Settings</h3>
        <p>Control how your radio station plays tracks and jingles</p>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update_playlist_settings">

            <!-- Playlist Mode -->
            <div class="form-group">
                <label for="radio_playlist_mode">Playlist Mode</label>
                <select name="radio_playlist_mode" id="radio_playlist_mode" class="form-control">
                    <option value="sequential" <?php echo ($station['radio_playlist_mode'] ?? 'shuffle') == 'sequential' ? 'selected' : ''; ?>>
                        Sequential (Play in order)
                    </option>
                    <option value="shuffle" <?php echo ($station['radio_playlist_mode'] ?? 'shuffle') == 'shuffle' ? 'selected' : ''; ?>>
                        Shuffle (Random order)
                    </option>
                    <option value="priority" <?php echo ($station['radio_playlist_mode'] ?? 'shuffle') == 'priority' ? 'selected' : ''; ?>>
                        Priority (Weighted by track priority)
                    </option>
                </select>
                <small class="form-text">How should audio tracks be played?</small>
            </div>

            <!-- Jingle Settings -->
            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="radio_jingle_enabled"
                                   name="radio_jingle_enabled" <?php echo ($station['radio_jingle_enabled'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="radio_jingle_enabled">
                                Enable Station ID & Jingles
                            </label>
                        </div>
                        <small class="form-text">Play jingles between tracks</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="radio_jingle_interval">Jingle Interval (Tracks)</label>
                        <input type="number" class="form-control" id="radio_jingle_interval"
                               name="radio_jingle_interval" min="1" max="100"
                               value="<?php echo $station['radio_jingle_interval'] ?? 5; ?>">
                        <small class="form-text">Play jingle after every N tracks</small>
                    </div>
                </div>
            </div>

            <!-- Advert Settings -->
            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="radio_advert_enabled"
                                   name="radio_advert_enabled" <?php echo ($station['radio_advert_enabled'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="radio_advert_enabled">
                                Enable Advertisements
                            </label>
                        </div>
                        <small class="form-text">Play advert jingles between tracks</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="radio_advert_interval">Advert Interval (Tracks)</label>
                        <input type="number" class="form-control" id="radio_advert_interval"
                               name="radio_advert_interval" min="1" max="100"
                               value="<?php echo $station['radio_advert_interval'] ?? 10; ?>">
                        <small class="form-text">Play advert after every N tracks</small>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="alert alert-info">
                <strong>💡 How It Works:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Jingles:</strong> Station IDs and jingles from your jingle library will be played automatically</li>
                    <li><strong>Adverts:</strong> Advertisement jingles will be inserted at the specified interval</li>
                    <li><strong>Upload Jingles:</strong> Go to the <a href="jingles.php">Jingles page</a> and mark them as "For Radio"</li>
                </ul>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Playlist Settings
                </button>
            </div>
        </form>
    </div>
</div>
```

## Steps to Complete:

1. **Run Database Migration:**
   ```sql
   -- In phpMyAdmin, run: database/migrations/phase6_radio_jingle_timing.sql
   ```

2. **Add UI Form:**
   - Open `dashboard/radio.php`
   - Find a good location (after the main radio settings form)
   - Add the HTML form code above

3. **Update Jingles Page:**
   - In `dashboard/jingles.php`, add a checkbox or option to mark jingles as "For Radio"
   - This will set the `is_for_radio` flag in the jingles table

4. **Test:**
   - Save playlist settings
   - Upload jingles marked for radio
   - Test playback with different intervals

## Notes:
- Intervals are validated between 1-100 tracks
- Settings are independent from TV jingle settings
- Jingles can be shared between TV and Radio or kept separate using the `is_for_radio` flag
