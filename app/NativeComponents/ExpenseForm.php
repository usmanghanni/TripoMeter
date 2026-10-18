<?php

namespace App\NativeComponents;

use App\Models\Expense;
use App\Models\Setting;
use App\Support\Odometer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Adds an expense (/expenses/add, /expenses/add/{category}) or edits and
 * deletes one (/expenses/{id}).
 */
class ExpenseForm extends NativeComponent
{
    public ?int $expenseId = null;

    public string $spentOn = '';

    public string $category = 'service';

    public string $title = '';

    public string $amount = '';

    public string $odometer = '';

    /**
     * Oil changes: the reading the next change is due at (service sticker).
     */
    public string $nextDueKm = '';

    public string $notes = '';

    /**
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public function mount(): void
    {
        $this->spentOn = today()->toDateString();

        if (array_key_exists((string) $this->param('category'), Expense::CATEGORIES)) {
            $this->category = $this->param('category');
        }

        $expense = $this->param('id') ? Expense::find($this->param('id')) : null;

        if ($expense) {
            $this->expenseId = $expense->id;
            $this->spentOn = $expense->spent_on->toDateString();
            $this->category = $expense->category;
            $this->title = (string) $expense->title;
            $this->amount = self::formatNumber($expense->amount);
            $this->odometer = $expense->odometer_km !== null ? self::formatNumber($expense->odometer_km) : '';
            $this->nextDueKm = $expense->next_due_km !== null ? self::formatNumber($expense->next_due_km) : '';
            $this->notes = (string) $expense->notes;
        }
    }

    public function navTitle(): string
    {
        return $this->expenseId ? 'Edit expense' : 'Add expense';
    }

    public function setCategory(string $category): void
    {
        if (array_key_exists($category, Expense::CATEGORIES)) {
            $this->category = $category;
        }
    }

    public function save(): void
    {
        $validator = Validator::make([
            'spent_on' => $this->spentOn,
            'category' => $this->category,
            'amount' => $this->amount === '' ? '0' : $this->amount,
            'odometer_km' => $this->odometer === '' ? null : $this->odometer,
            'next_due_km' => $this->category !== 'oil_change' || trim($this->nextDueKm) === '' ? null : trim($this->nextDueKm),
        ], [
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'amount' => ['numeric', 'min:0'],
            'odometer_km' => [Rule::requiredIf($this->category === 'oil_change'), 'nullable', 'numeric', 'gt:0'],
            'next_due_km' => ['nullable', 'numeric', 'gt:odometer_km'],
        ], [
            'odometer_km.required' => 'Enter the odometer so the next oil change can be worked out.',
            'next_due_km.gt' => 'The next change must be at a higher reading than this one.',
        ], [
            'odometer_km' => 'odometer',
            'next_due_km' => 'next change reading',
        ]);

        if ($validator->fails()) {
            $this->fieldErrors = array_map(fn (array $messages): string => $messages[0], $validator->errors()->toArray());

            return;
        }

        Expense::updateOrCreate(['id' => $this->expenseId], [
            ...$validator->validated(),
            'title' => trim($this->title) ?: null,
            'notes' => trim($this->notes) ?: null,
        ]);

        $this->back();
    }

    public function confirmDelete(): void
    {
        Dialog::alert('Delete expense?', 'This removes it from your expenses.', ['Cancel', 'Delete'])
            ->id('delete-expense')
            ->buttonPressed(function (object $event): void {
                if (($event->label ?? null) === 'Delete') {
                    Expense::whereKey($this->expenseId)->delete();
                    $this->back();
                }
            });
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function render(): View
    {
        $intervalKm = Setting::current()->oil_change_interval_km ?? 5000;

        return view('native.expense-form', [
            'categories' => Expense::CATEGORIES,
            'estimatedOdometer' => $this->expenseId ? null : Odometer::current()['km'] ?? null,
            'suggestedNextDueKm' => is_numeric($this->odometer) && $intervalKm > 0 ? (float) $this->odometer + $intervalKm : null,
        ]);
    }
}
