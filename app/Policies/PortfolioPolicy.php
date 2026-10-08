<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    /**
     * Determine whether the user can view any portfolios.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the portfolio.
     */
    public function view(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }

    /**
     * Determine whether the user can create a portfolio.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the portfolio.
     */
    public function update(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }

    /**
     * Determine whether the user can delete the portfolio.
     */
    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }
}
