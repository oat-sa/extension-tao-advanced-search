<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\DeliveryResult\Normalizer;

use DateTimeImmutable;
use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\helpers\UserHelper;
use oat\taoAdvancedSearch\model\Index\IndexResource;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoDelivery\model\execution\DeliveryExecutionInterface;
//use oat\taoOutcomeUi\model\search\ResultCustomFieldsService;
use oat\taoResultServer\models\classes\ResultService;

class DeliveryResultNormalizer extends ConfigurableService implements NormalizerInterface
{
    public const INDEX_DELIVERY = 'delivery';
    public const INDEX_TEST_TAKER = 'test_taker';
    public const INDEX_TEST_TAKER_NAME = 'test_taker_name';
    public const INDEX_TEST_TAKER_LABEL = 'test_taker_label';
    public const INDEX_DELIVERY_EXECUTION = 'delivery_execution';
    public const INDEX_DELIVERY_EXECUTION_START_TIME = 'delivery_execution_start_time';
    public const INDEX_TEST_TAKER_LAST_NAME = 'test_taker_last_name';
    public const INDEX_TEST_TAKER_FIRST_NAME = 'test_taker_first_name';

    public function normalize($deliveryExecution): IndexResource
    {
        if (!$deliveryExecution instanceof DeliveryExecutionInterface) {
            throw new InvalidArgumentException(
                '$deliveryExecution must be instance of ' . DeliveryExecutionInterface::class
            );
        }

        $deliveryExecutionId = $deliveryExecution->getIdentifier();
        $user = UserHelper::getUser($deliveryExecution->getUserIdentifier());

//        $customFieldService = $this->getResultCustomFieldsService();
//        $customBody = $customFieldService->getCustomFields($deliveryExecution);

        return new IndexResource(
            $deliveryExecutionId,
            $deliveryExecution->getLabel(),
//            array_merge(
                [
                    'label' => $deliveryExecution->getLabel(),
                    self::INDEX_DELIVERY => $deliveryExecution->getDelivery()->getUri(),
                    'type' => ResultService::DELIVERY_RESULT_CLASS_URI,
                    self::INDEX_TEST_TAKER => $user->getIdentifier(),
                    self::INDEX_TEST_TAKER_FIRST_NAME => UserHelper::getUserFirstName($user, true),
                    self::INDEX_TEST_TAKER_LAST_NAME => UserHelper::getUserLastName($user, true),
                    self::INDEX_TEST_TAKER_NAME => UserHelper::getUserName($user, true),
                    self::INDEX_TEST_TAKER_LABEL => UserHelper::getUserLabel($user),
                    self::INDEX_DELIVERY_EXECUTION => $deliveryExecutionId,
                    self::INDEX_DELIVERY_EXECUTION_START_TIME => $this->transformDateTime(
                        $deliveryExecution->getStartTime()
                    )
                ]
//                $customBody
//            )
        );
    }

    private function transformDateTime(string $getStartTime): string
    {
        $timeArray = explode(" ", $getStartTime);
        $date = DateTimeImmutable::createFromFormat('U', $timeArray[1]);

        if ($date === false) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $getStartTime);
        }

        if (!$date instanceof DateTimeImmutable) {
            $this->logCritical(
                sprintf('We were not able to transform string: "%s" delivery-execution start time!', $getStartTime)
            );
            return '';
        }

        return $date->format('m/d/Y H:i:s');
    }

//    private function getResultCustomFieldsService(): ResultCustomFieldsService
//    {
//        return $this->getServiceLocator()->get(ResultCustomFieldsService::SERVICE_ID);
//    }
}
