# Station Type System - TV, Radio, or Both

## Overview

Users can now choose what type of broadcasting they want to do when creating their station:
- **TV Station** (Video Broadcasting Only)
- **Radio Station** (Audio Broadcasting Only)
- **Both TV & Radio** (Full Access)

This gives users flexibility and fair pricing based on their needs.

---

## Pricing Structure

| Station Type | Cost (Coins) | Features Available |
|--------------|--------------|-------------------|
| **TV Only** | 100 coins | Videos, Jingles, Live TV Player, Tickers, Display Settings |
| **Radio Only** | 100 coins | Audio Library, Radio Streams, Radio Schedule, Now Playing |
| **Both TV & Radio** | 150 coins | ALL Features (Best Value!) |

**Why "Both" costs more:**
- 50% more than single type (150 vs 100 coins)
- Access to BOTH TV and Radio features
- Better value than creating two separate stations (would cost 200 coins)

---

## How It Works

### 1. Station Creation
When users create their station at `/dashboard/create-station.php`:
1. Select station type from dropdown
2. See cost update dynamically based on selection
3. Pay appropriate coins (100 or 150)
4. Station created with selected type

### 2. Access Control
- **TV-only users**: Can access all video-related features, CANNOT access radio features
- **Radio-only users**: Can access all audio-related features, CANNOT access video features
- **Both users**: Can access everything

### 3. Radio Access Check
When user tries to access `/dashboard/radio.php`:
- System checks `station_type` from database
- If type is NOT 'radio' or 'both', redirect with message:
  > "Radio features are not available. You created a TV-only station. Please contact admin to upgrade to 'Both TV & Radio'."

---

## Database Schema

### Migration File
`database/migrations/phase9_add_station_type.sql`

### Column Added
```sql
ALTER TABLE stations
ADD COLUMN station_type ENUM('tv', 'radio', 'both') DEFAULT 'tv'
COMMENT 'Type of station: TV only, Radio only, or Both';
```

### Existing Stations
All existing stations are updated to `'both'` so they retain full access:
```sql
UPDATE stations SET station_type = 'both'
WHERE station_type IS NULL OR station_type = '';
```

---

## Feature Access Matrix

| Feature | TV Only | Radio Only | Both |
|---------|---------|------------|------|
| Upload Videos | ✅ | ❌ | ✅ |
| Live TV Player | ✅ | ❌ | ✅ |
| Jingles | ✅ | ❌ | ✅ |
| Tickers | ✅ | ❌ | ✅ |
| Display Settings | ✅ | ❌ | ✅ |
| Radio Audio Library | ❌ | ✅ | ✅ |
| Radio Streams | ❌ | ✅ | ✅ |
| Radio Schedule | ❌ | ✅ | ✅ |
| Now Playing | ❌ | ✅ | ✅ |

---

## User Flows

### Scenario 1: New User Wants TV Only
1. Register account (0 coins)
2. Purchase coins (e.g., Basic package ₦10,000 = 1,100 coins)
3. Admin approves → User has 1,100 coins
4. Go to Create Station
5. Select "TV Station (Video Broadcasting) - 100 coins"
6. Click Create
7. **Result**: TV station created, 1,000 coins remaining, CAN access videos, CANNOT access radio

### Scenario 2: New User Wants Radio Only
1. Register account (0 coins)
2. Purchase coins (e.g., Basic package ₦10,000 = 1,100 coins)
3. Admin approves → User has 1,100 coins
4. Go to Create Station
5. Select "Radio Station (Audio Only) - 100 coins"
6. Click Create
7. **Result**: Radio station created, 1,000 coins remaining, CAN access radio, CANNOT access videos

### Scenario 3: New User Wants Both (Smart Choice!)
1. Register account (0 coins)
2. Purchase coins (e.g., Basic package ₦10,000 = 1,100 coins)
3. Admin approves → User has 1,100 coins
4. Go to Create Station
5. Select "Both TV & Radio - 150 coins (Best Value!)"
6. Click Create
7. **Result**: Full station created, 950 coins remaining, CAN access everything

---

## Upgrading Station Type

### Option 1: Contact Admin (Manual)
User contacts admin who can manually update database:
```sql
UPDATE stations SET station_type = 'both' WHERE id = ?;
```

### Option 2: Self-Service Upgrade (Future Feature)
Could add upgrade page:
- TV → Both: Pay 50 coins (difference)
- Radio → Both: Pay 50 coins (difference)
- TV → Radio: Not allowed (must contact admin)
- Radio → TV: Not allowed (must contact admin)

---

## Coin Deduction for Activities

### TV Station Activities (Require TV or Both):
- Video upload: 10 coins
- (Future: Storage, streaming hours)

### Radio Station Activities (Require Radio or Both):
- Audio upload: 10 coins
- Stream setup: 20 coins (one-time)
- (Future: Storage, streaming hours)

---

## Benefits

### For Users
✅ **Flexibility**: Choose only what you need
✅ **Fair pricing**: Don't pay for features you won't use
✅ **Upgrade option**: Can upgrade to "Both" later if needs change
✅ **Clear access**: Know exactly what features you can use

### For Platform Owner
✅ **More signups**: Lower entry cost (100 coins vs 150)
✅ **Upsell opportunity**: Users may upgrade to "Both" later
✅ **Fair value**: Users pay for what they get
✅ **Clear offering**: Easy to explain to clients

---

## Files Modified

1. **database/migrations/phase9_add_station_type.sql** - Database migration
2. **dashboard/create-station.php** - Updated pricing and type selection
3. **dashboard/radio.php** - Added access control check

---

## Testing Checklist

### Test Station Creation
- [ ] Create TV-only station (100 coins deducted)
- [ ] Create Radio-only station (100 coins deducted)
- [ ] Create Both station (150 coins deducted)
- [ ] Verify coin balance updates correctly
- [ ] Check station_type saved in database

### Test Access Control
- [ ] TV-only user can access /dashboard/videos.php
- [ ] TV-only user CANNOT access /dashboard/radio.php (gets redirected)
- [ ] Radio-only user can access /dashboard/radio.php
- [ ] Radio-only user CANNOT access /dashboard/videos.php (should add check)
- [ ] Both user can access everything

### Test Existing Stations
- [ ] Existing stations have station_type = 'both'
- [ ] Existing stations can access all features
- [ ] No disruption to current users

---

## Next Steps (Optional Enhancements)

1. **Add Video Access Control**: Block radio-only users from uploading videos
2. **Self-Service Upgrade**: Allow users to upgrade station type for 50 coins
3. **Dashboard Visibility**: Hide TV/Radio menu items based on station type
4. **Admin Panel**: Add station type filter and upgrade button
5. **Analytics**: Track which station types are most popular

---

**Date**: December 31, 2025
**Status**: READY FOR DATABASE MIGRATION
**Migration Required**: Run `phase9_add_station_type.sql`

## Important: Run Migration First!

Before testing, you MUST run the migration:
```bash
mysql -u your_user -p fdtv_db < database/migrations/phase9_add_station_type.sql
```

This adds the `station_type` column that the system now requires.
