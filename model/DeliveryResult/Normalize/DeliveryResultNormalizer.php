<?php

namespace oat\taoAdvancedSearch\model\DeliveryResult\Normalizer;

use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\helpers\UserHelper;
use oat\taoAdvancedSearch\model\Index\IndexResource;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoDelivery\model\execution\DeliveryExecutionInterface;
use oat\taoResultServer\models\classes\ResultService;

class DeliveryResultNormalizer extends ConfigurableService implements NormalizerInterface
{
    public const INDEX_DELIVERY = 'delivery';
    public const INDEX_TEST_TAKER = 'test_taker';
    public const INDEX_TEST_TAKER_NAME = 'test_taker_name';
    public const INDEX_DELIVERY_EXECUTION = 'delivery_execution';
    public const INDEX_TEST_TAKER_LAST_NAME = 'test_taker_last_name';

    public function normalize($deliveryExecution): IndexResource
    {
        if (!$deliveryExecution instanceof DeliveryExecutionInterface) {
            throw new InvalidArgumentException(
                '$deliveryExecution must be instance of ' . DeliveryExecutionInterface::class
            );
        }

        $deliveryExecutionId = $deliveryExecution->getIdentifier();
        $user = UserHelper::getUser($deliveryExecution->getUserIdentifier());
        $userName = UserHelper::getUserName($user, true);

        return new IndexResource(
            $deliveryExecutionId,
            $deliveryExecution->getLabel(),
            [
                'label' => $deliveryExecution->getLabel(),
                self::INDEX_DELIVERY => $deliveryExecution->getDelivery()->getUri(),
                'type' => ResultService::DELIVERY_RESULT_CLASS_URI,
                self::INDEX_TEST_TAKER => $user->getIdentifier(),
                self::INDEX_TEST_TAKER_NAME => $userName,
                self::INDEX_DELIVERY_EXECUTION => $deliveryExecutionId,
            ]
        );
    }
}