<?php

namespace App\Enums;

enum Role: string
{
    case Teaching = 'teaching';
    case NonTeaching = 'nonteaching';
    case DepartmentHead = 'depthead';
    case Dean = 'dean';
    case Hr = 'hr';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Teaching => 'Teaching Employee',
            self::NonTeaching => 'Non-Teaching Employee',
            self::DepartmentHead => 'Department Head',
            self::Dean => 'Dean/Director',
            self::Hr => 'HR Personnel',
            self::Admin => 'Administrator',
        };
    }

    /** Route-name and URL prefix for this role's own pages. */
    public function prefix(): string
    {
        return match ($this) {
            self::Teaching, self::NonTeaching => 'employee',
            default => $this->value,
        };
    }

    public function isEmployee(): bool
    {
        return in_array($this, [self::Teaching, self::NonTeaching], true);
    }

    public function isReviewer(): bool
    {
        return in_array($this, [self::DepartmentHead, self::Dean, self::Hr], true);
    }

    /** The employee type an account of this role is evaluated as, if any. */
    public function employeeType(): ?string
    {
        return match ($this) {
            self::Teaching => 'teaching',
            self::NonTeaching => 'nonteaching',
            default => null,
        };
    }

    /** The IPCR status that means "waiting at this reviewer's step". */
    public function reviewStatus(): ?IpcrStatus
    {
        return match ($this) {
            self::DepartmentHead => IpcrStatus::Submitted,
            self::Dean => IpcrStatus::WithDean,
            self::Hr => IpcrStatus::WithHr,
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
