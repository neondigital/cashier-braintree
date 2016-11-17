# Laravel Doctrine Cashier

This is a fork and adaptation of the (https://github.com/laravel/cashier)[Laravel Cashier] package for use with Doctrine.

## Introduction

Add `Neondigital\Cashier\CashierServiceProvider::class` to your `config/app.php` file.

## Users

Your user entity should use the `Neondigital\Cashier\Billable` trait and have the following properties.

```php
<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use Neondigital\Cashier\Billable;

/**
 * @ORM\Entity
 */
class User
{
    use Billable;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

        /**
     * @ORM\Column(type="string",nullable=true)
     */
    protected $stripeId;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $cardBrand;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $cardLastFour;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $trialEndsAt;

    /**
     * Gets the value of stripeId.
     *
     * @return mixed
     */
    public function getStripeId()
    {
        return $this->stripeId;
    }

    /**
     * Sets the value of stripeId.
     *
     * @param mixed $stripeId the stripe id
     *
     * @return self
     */
    public function setStripeId($stripeId)
    {
        $this->stripeId = $stripeId;

        return $this;
    }

    /**
     * Gets the value of cardBrand.
     *
     * @return mixed
     */
    public function getCardBrand()
    {
        return $this->cardBrand;
    }

    /**
     * Sets the value of cardBrand.
     *
     * @param mixed $cardBrand the card brand
     *
     * @return self
     */
    public function setCardBrand($cardBrand)
    {
        $this->cardBrand = $cardBrand;

        return $this;
    }

    /**
     * Gets the value of cardLastFour.
     *
     * @return mixed
     */
    public function getCardLastFour()
    {
        return $this->cardLastFour;
    }

    /**
     * Sets the value of cardLastFour.
     *
     * @param mixed $cardLastFour the card last four
     *
     * @return self
     */
    public function setCardLastFour($cardLastFour)
    {
        $this->cardLastFour = $cardLastFour;

        return $this;
    }

    /**
     * Gets the value of trialEndsAt.
     *
     * @return mixed
     */
    public function getTrialEndsAt()
    {
        return $this->trialEndsAt;
    }

    /**
     * Sets the value of trialEndsAt.
     *
     * @param mixed $trialEndsAt the trial ends at
     *
     * @return self
     */
    public function setTrialEndsAt($trialEndsAt)
    {
        $this->trialEndsAt = $trialEndsAt;

        return $this;
    }
}
```
