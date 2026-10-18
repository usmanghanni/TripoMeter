<?php

namespace App\NativeComponents;

use App\Models\Expense;
use App\Support\Odometer;
use App\Support\OilChange;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

/**
 * Car expenses other than fuel, with this year's totals by category and when
 * the next oil change is due.
 */
class ExpenseLog extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Expenses';
    }

    /**
     * @return Collection<int, Expense>
     */
    #[Computed]
    public function expenses(): Collection
    {
        return Expense::orderByDesc('spent_on')->orderByDesc('id')->get();
    }

    /**
     * @return array<string, float>
     */
    #[Computed]
    public function yearTotals(): array
    {
        return Expense::totalsByCategory(today()->startOfYear());
    }

    /**
     * @return array{last_on: CarbonInterface, last_km: float|null, due_km: float|null, remaining_km: float|null, due_on: CarbonInterface|null, days_left: int|null, fraction_left: float, is_due_soon: bool, is_overdue: bool}|null
     */
    #[Computed]
    public function oilChange(): ?array
    {
        return OilChange::status();
    }

    /**
     * @return array{km: float, reading_km: float, read_on: CarbonInterface, driven_since_km: float}|null
     */
    #[Computed]
    public function odometer(): ?array
    {
        return Odometer::current();
    }

    public function addExpense(): void
    {
        $this->navigate('/expenses/add');
    }

    public function addOilChange(): void
    {
        $this->navigate('/expenses/add/oil_change');
    }

    public function openExpense(int $expenseId): void
    {
        $this->navigate("/expenses/{$expenseId}");
    }

    public function deleteExpense(int $expenseId): void
    {
        Expense::whereKey($expenseId)->delete();
    }

    public function render(): View
    {
        return view('native.expense-log');
    }
}
