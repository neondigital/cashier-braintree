<?php

namespace Neondigital\Cashier\Http\Controllers;

use Exception;
use EntityManager;
use Illuminate\Http\Request;
use App\Entities\Subscription;
use Illuminate\Routing\Controller;
use Braintree\WebhookNotification;
use App\Services\HomingpinService;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Braintree webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        try {
            $webhook = $this->parseBraintreeNotification($request);
        } catch (Exception $e) {
            return;
        }

        $method = 'handle'.studly_case(str_replace('.', '_', $webhook->kind));

        if (method_exists($this, $method)) {
            return $this->{$method}($webhook);
        } else {
            return $this->missingMethod();
        }
    }

    /**
     * Parse the given Braintree webhook notification request.
     *
     * @param  Request  $request
     * @return WebhookNotification
     */
    protected function parseBraintreeNotification($request)
    {
        return WebhookNotification::parse($request->bt_signature, $request->bt_payload);
    }

    protected function handleSubscriptionChargedSuccessfully($webhook)
    {
        $subscription = $this->getSubscriptionById($webhook->subscription->id);
        $user = $subscription->getUser();

        // Update the users HomingPINs
        (new HomingpinService)->refreshHomingPinsForUser(
            $user,
            $webhook->subscription->nextBillingDate
        );

        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle a subscription cancellation notification from Braintree.
     *
     * @param  WebhookNotification  $webhook
     * @return \Illuminate\Http\Response
     */
    protected function handleSubscriptionCanceled($webhook)
    {
        return $this->cancelSubscription($webhook->subscription->id);
    }

    /**
     * Handle a subscription expiration notification from Braintree.
     *
     * @param  WebhookNotification  $webhook
     * @return \Illuminate\Http\Response
     */
    protected function handleSubscriptionExpired($webhook)
    {
        return $this->cancelSubscription($webhook->subscription->id);
    }

    /**
     * Handle a subscription cancellation notification from Braintree.
     *
     * @param  string  $subscriptionId
     * @return \Illuminate\Http\Response
     */
    protected function cancelSubscription($subscriptionId)
    {
        $subscription = $this->getSubscriptionById($subscriptionId);

        if ($subscription && (! $subscription->cancelled() || $subscription->onGracePeriod())) {
            $subscription->markAsCancelled();
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Get the model for the given subscription ID.
     *
     * @param  string  $subscriptionId
     * @return mixed
     */
    protected function getSubscriptionById($subscriptionId)
    {
        $repo = EntityManager::getRepository(Subscription::class);
        return $repo->getByBraintreeId($subscriptionId);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array   $parameters
     * @return mixed
     */
    public function missingMethod($parameters = [])
    {
        return new Response;
    }
}
