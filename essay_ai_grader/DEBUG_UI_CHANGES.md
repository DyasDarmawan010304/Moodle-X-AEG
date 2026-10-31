# Debug UI Changes - May 17, 2026

## Perubahan yang Dilakukan

### 1. Version Bump
- **File**: `version.php`
- **Dari**: 2026041906
- **Ke**: 2026051701
- **Tujuan**: Force Moodle cache invalidation

### 2. Explicit Class Loading
- **File**: `lib.php` (line ~26)
- **Tambahan**: `require_once(dirname(__FILE__) . '/classes/criteria_config_form.php');`
- **Tujuan**: Ensure criteria_config_form class is loaded

### 3. Debug Logging
Ditambahkan debug logging di:
- `lib.php` - hook function `local_essay_ai_grader_coursemodule_standard_elements()`
- `criteria_config_form.php` - method `add_criteria_fields_to_form()`
- `criteria_config_form.php` - method `add_five_aspects_rubric_fields_to_form()`

## Langkah Debugging

### Step 1: Purge Moodle Cache
Akses: Admin → Development → Purge Caches

Atau via CLI:
```bash
php admin/cli/purge_caches.php
```

### Step 2: Check Error Log
```bash
# Linux/WSL
tail -f /var/log/apache2/error.log
# atau
tail -f /path/to/moodle/logs/

# Windows (jika menggunakan PHP built-in server)
# Check browser console atau output dari PHP
```

### Step 3: Edit Existing Assignment
1. Login sebagai admin
2. Masuk ke suatu assignment yang sudah ada
3. Klik "Edit assignment settings"
4. Scroll down untuk mencari form section
5. Buka browser console (F12) dan check console logs

### Step 4: Check Debug Logs
Cari di PHP error log untuk messages:
- `DEBUG: essay_ai_grader hook called`
- `DEBUG: Adding 5-aspect form`
- `DEBUG: Adding 6-aspect form`
- `DEBUG: add_criteria_fields_to_form called`
- `DEBUG: add_five_aspects_rubric_fields_to_form called`

## Expected Output Sequence

**Untuk Assignment Baru:**
```
DEBUG: essay_ai_grader hook called - assignmentid=0, rubric_type=null
DEBUG: Adding 6-aspect form for assignment 0
DEBUG: add_criteria_fields_to_form called for assignment 0
DEBUG: Config found: no
DEBUG: Header element added
DEBUG: Enable checkbox added, default=1
DEBUG: Max score field added
```

**Untuk Assignment dengan 5-Aspect:**
```
DEBUG: essay_ai_grader hook called - assignmentid=123, rubric_type=five_aspects
DEBUG: Adding 5-aspect form for assignment 123
DEBUG: add_five_aspects_rubric_fields_to_form called for assignment 123
DEBUG: Config found: yes
DEBUG: Header element added
DEBUG: Enable checkbox added, default=1
```

## Troubleshooting

### Problem: Hook tidak dipanggil
**Solution**: 
1. Verify hook function name: `local_essay_ai_grader_coursemodule_standard_elements`
2. Check if plugin di-enable di Moodle admin
3. Verify class namespace: `local_essay_ai_grader\criteria_config_form`

### Problem: Exception di try-catch
Check error log untuk "ERROR in essay_ai_grader hook" - akan menampilkan exception message

### Problem: Form field tidak muncul di UI
1. Check browser console untuk JavaScript errors
2. Check if CSS file dimuat: `/local/essay_ai_grader/styles/form-criteria.css`
3. Verify form element names tidak conflict dengan form element lain

## Manual Form Test

Untuk testing tanpa depend pada hook, bisa create test script:

```php
<?php
require_once('../../config.php');
require_login();

$assignment = new stdClass();
$assignment->id = 1;

$mform = new MoodleQuickForm('testform');

// Try calling form method directly
\local_essay_ai_grader\criteria_config_form::add_criteria_fields_to_form($mform, $assignment);

// Check if elements added
$elements = $mform->getElements();
echo "Total elements: " . count($elements) . "\n";
foreach ($elements as $elem) {
    echo "- " . $elem->getName() . "\n";
}
?>
```

## Next Steps

1. ✅ Updated version number for cache invalidation
2. ✅ Added explicit class loading in lib.php
3. ✅ Added comprehensive debug logging
4. ⏳ Run cache purge and test
5. ⏳ Check debug logs to identify issue
6. ⏳ Adjust based on logs

**Silakan jalankan langkah debugging di atas dan share output error log atau browser console yang ditemukan!**
