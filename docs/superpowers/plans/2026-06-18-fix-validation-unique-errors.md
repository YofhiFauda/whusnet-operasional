# Fix Intermittent Validation Unique Errors for POPs and Distributions

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve intermittent `validation.unique` errors in POP and Distribution creation by implementing input normalization, composite unique constraints, and UI double-submit protection.

**Architecture:** 
1. Normalize 'code' inputs to uppercase and trimmed in controllers.
2. Change `distributions.code` uniqueness from global to per-POP using a composite unique index.
3. Update validation rules in `DistributionController` to use `Rule::unique()->where('pop_id', $pop_id)`.
4. Implement frontend debounce/disable on submit buttons.

**Tech Stack:** Laravel, PHP, MySQL, Blade, JavaScript.

## Global Constraints
- Follow existing Laravel coding standards.
- Ensure TDD approach: write failing tests first.
- Normalize inputs BEFORE validation.
- No soft deletes are currently used, but keep the logic extensible.

---

### Task 1: Normalize Input in PopController

**Files:**
- Modify: `app/Http/Controllers/Master/PopController.php`
- Test: `tests/Feature/Master/PopTest.php` (create if not exists)

**Interfaces:**
- Produces: `normalizeIdentifierInput` updated to handle `code` and `pop_code`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Master/PopTest.php
public function test_it_normalizes_pop_code_and_name_before_validation()
{
    $user = User::factory()->create(); // Ensure user has permissions
    $response = $this->actingAs($user)->post(route('pop.store'), [
        'pop_code' => ' pop-01 ',
        'pop_name' => ' POP Name ',
    ]);
    
    $this->assertDatabaseHas('pops', [
        'pop_code' => 'POP-01',
        'pop_name' => 'POP Name',
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Feature/Master/PopTest.php`

- [ ] **Step 3: Implement normalization**
Update `normalizeIdentifierInput` in `PopController.php` to include `code` (if applicable) and ensure `pop_code` is uppered/trimmed.

- [ ] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Feature/Master/PopTest.php`

- [ ] **Step 5: Commit**
`git add app/Http/Controllers/Master/PopController.php tests/Feature/Master/PopTest.php && git commit -m "fix(pop): normalize input before validation"`

---

### Task 2: Normalize Input and Update Uniqueness in DistributionController

**Files:**
- Modify: `app/Http/Controllers/Master/DistributionController.php`
- Test: `tests/Feature/Master/DistributionTest.php`

**Interfaces:**
- Consumes: `pop_id` from request.
- Produces: Normalized `code` and scoped uniqueness check.

- [ ] **Step 1: Write the failing test for composite uniqueness**

```php
// tests/Feature/Master/DistributionTest.php
public function test_it_allows_same_code_in_different_pops()
{
    $pop1 = Pop::factory()->create(['pop_code' => 'P1']);
    $pop2 = Pop::factory()->create(['pop_code' => 'P2']);
    
    Distribution::create(['pop_id' => $pop1->id, 'code' => 'D1', 'name' => 'Dist 1']);
    
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post(route('distribusi.store'), [
        'pop_id' => $pop2->id,
        'code' => 'd1', // Should be normalized to D1
        'name' => 'Dist 2',
    ]);
    
    $response->assertStatus(302);
    $this->assertDatabaseHas('distributions', ['pop_id' => $pop2->id, 'code' => 'D1']);
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Feature/Master/DistributionTest.php`

- [ ] **Step 3: Update Controller**
Update `store` and `update` methods to:
1. Normalize `code` (trim + strtoupper).
2. Update validation to use `Rule::unique('distributions')->where('pop_id', $request->pop_id)->ignore($id)`.

- [ ] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Feature/Master/DistributionTest.php`

- [ ] **Step 5: Commit**
`git commit -am "fix(distribusi): normalize input and scope uniqueness to pop_id"`

---

### Task 3: Update Database Constraint for Distributions

**Files:**
- Create: `database/migrations/2026_06_18_000000_update_distributions_unique_index.php`

- [ ] **Step 1: Create migration**

```php
public function up()
{
    Schema::table('distributions', function (Blueprint $table) {
        $table->dropUnique(['code']); // Drop old global unique index
        $table->unique(['pop_id', 'code']); // Add composite unique index
    });
}
```

- [ ] **Step 2: Run migration**
Run: `php artisan migrate`

- [ ] **Step 3: Commit**
`git add database/migrations/... && git commit -m "db: update distributions unique index to composite pop_id and code"`

---

### Task 4: Add Double-Submit Protection to UI

**Files:**
- Modify: `resources/views/master/distribusi/create.blade.php`
- Modify: `resources/views/master/pop/create.blade.php`

- [ ] **Step 1: Add JS to disable button on submit**

```javascript
// Add to the bottom of the blade files
<script>
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
        }
    });
});
</script>
```

- [ ] **Step 2: Verify manually**
Try clicking submit multiple times quickly.

- [ ] **Step 3: Commit**
`git commit -am "ui: add double-submit protection to pop and distribution forms"`
