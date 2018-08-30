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
use App\Events\UserSubscriptionRefreshedEvent;
use App\Services\Notifications\OutgoingService;

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

        $subscription = $this->getSubscriptionById($webhook->subscription->id);

        if (method_exists($this, $method)) {
            $result = $this->{$method}($webhook);
        } else {
            $result = $this->missingMethod();
        }


        return $result;
    }

    public function generateTest(Request $request)
    {
        /**
         * Available test codes:
         *
         * subscription_went_past_due
         * subscription_canceled
         * subscription_charged_unsuccessfully
         * subscription_charged_successfully
         * subscription_expired
         */
        $sampleNotification = \Braintree\WebhookTesting::sampleNotification(
            $request->type,
            $request->braintree_id
        );

        return response()->json($sampleNotification);
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

    protected function handleSubscriptionChargedUnsuccessfully($webhook)
    {
        $subscription = $this->getSubscriptionById($webhook->subscription->id);
        $user = $subscription->getUser();

        $notification = new OutgoingService;
        $notification->addUser($user);
        $notification->setSubject(
            trans(
                'notifications/email/subscription_payment_failed.subject',
                [],
                null,
                $user->getLocale()
            )
        );
        $notification->sendEmail('subscription_payment_failed');

        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle the request for a successful payment
     *
     * @param mixed $webhook
     * @return void
     */
    protected function handleSubscriptionChargedSuccessfully($webhook)
    {
        $subscription = $this->getSubscriptionById($webhook->subscription->id);
        $user = $subscription->getUser();

        // If the user isn't subscribed, resume.
        if (!$user->subscribed('HomingPIN') && $user->subscription('HomingPIN')->onGracePeriod()) {
            $user->subscription('HomingPIN')->resume();
        }

        // Update the users HomingPINs to the next billing date
        (new HomingpinService)->refreshHomingPinsForUser(
            $user,
            $subscription->getDetails()->nextBillingDate
        );

        $notification = new OutgoingService;
        $notification->addUser($user);
        $notification->setContent([
            'next_renewal' => $subscription->getDetails()->nextBillingDate,
        ]);

        $notification->setSubject(
            trans(
                'notifications/email/subscription_renewed.subject',
                [],
                null,
                $user->getLocale()
            )
        );
        $notification->sendEmail('subscription_renewed');

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
        $user = $subscription->getUser();

        if ($subscription && (! $subscription->cancelled() || $subscription->onGracePeriod())) {

            $notification = new OutgoingService;
            $notification->addUser($user);

            $notification->setSubject(
                trans(
                    'notifications/email/subscription_cancelled.subject',
                    [],
                    null,
                    $user->getLocale()
                )
            );
            $notification->sendEmail('subscription_cancelled');

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
