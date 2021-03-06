<?php

/*
 * This file is part of the overtrue/easy-sms.
 * (c) overtrue <i@overtrue.me>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\EasySms;

use Overtrue\EasySms\Contracts\MessageInterface;

/**
 * Class Messenger.
 */
class Messenger
{
    const STATUS_SUCCESS = 'success';
    const STATUS_ERRED = 'erred';

    /**
     * @var \Overtrue\EasySms\EasySms
     */
    protected $easySms;

    /**
     * Messenger constructor.
     *
     * @param \Overtrue\EasySms\EasySms $easySms
     */
    public function __construct(EasySms $easySms)
    {
        $this->easySms = $easySms;
    }

    /**
     * Send a message.
     *
     * @param string|array                                 $to
     * @param \Overtrue\EasySms\Contracts\MessageInterface $message
     * @param array                                        $gateways
     *
     * @return array
     */
    public function send($to, MessageInterface $message, array $gateways = [])
    {
        if (!($message instanceof MessageInterface)) {
            $message = new Message([
                'content' => $message,
                'template' => $message,
            ]);
        }

        if (empty($gateways)) {
            $gateways = $message->getGateways();
        }

        if (empty($gateways)) {
            $gateways = $this->easySms->getConfig()->get('default.gateways', []);
        }

        $gateways = $this->formatGateways($gateways);
        $strategyAppliedGateways = $this->easySms->strategy()->apply($gateways);

        $results = [];
        foreach ($strategyAppliedGateways as $gateway) {
            try {
                $results[$gateway] = [
                        'status' => self::STATUS_SUCCESS,
                        'result' => $this->easySms->gateway($gateway)->send($to, $message, new Config($gateways[$gateway])),
                    ];
            } catch (GatewayErrorException $e) {
                $results[$gateway] = [
                    'status' => self::STATUS_ERRED,
                    'exception' => $e,
                ];
                continue;
            }
        }

        return $results;
    }

    /**
     * @param array $gateways
     *
     * @return array
     */
    protected function formatGateways(array $gateways)
    {
        $formatted = [];
        $config = $this->easySms->getConfig();

        foreach ($gateways as $gateway => $setting) {
            if (is_integer($gateway) && is_string($setting)) {
                $gateway = $setting;
                $setting = [];
            }

            $globalSetting = $config->get("gateways.{$gateway}", []);

            if (is_string($gateway) && !empty($globalSetting) && is_array($setting)) {
                $formatted[$gateway] = array_merge($globalSetting, $setting);
            }
        }

        return $formatted;
    }
}
