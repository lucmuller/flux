<?php
namespace FluidTYPO3\Flux\Outlet\Pipe;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\StringConverter;
use TYPO3\CMS\Extbase\Property\TypeConverterInterface;

/**
 * Standard Input Pipe
 *
 * Accepts POST array form data and uses a Flux Form
 * to perform pre-saving steps (validation, transformation etc).
 */
class TypeConverterPipe extends AbstractPipe implements PipeInterface
{
    protected ObjectManagerInterface $objectManager;
    protected ?TypeConverterInterface $typeConverter = null;

    protected ?string $targetType = null;
    protected ?string $propertyName = null;

    public function injectObjectManager(ObjectManagerInterface $objectManager): void
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param TypeConverterInterface|class-string $typeConverter
     */
    public function setTypeConverter($typeConverter): self
    {
        if (is_string($typeConverter)) {
            /** @var TypeConverterInterface $typeConverter */
            $typeConverter = $this->objectManager->get($typeConverter);
        }
        $this->typeConverter = $typeConverter;
        return $this;
    }

    public function getTypeConverter(): TypeConverterInterface
    {
        return $this->typeConverter ?? new StringConverter();
    }

    public function setTargetType(?string $targetType): self
    {
        $this->targetType = $targetType;
        return $this;
    }

    public function getTargetType(): string
    {
        return $this->targetType ?? 'string';
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }

    public function setPropertyName(?string $propertyName): self
    {
        $this->propertyName = $propertyName;

        return $this;
    }

    /**
     * @param mixed $data
     * @return mixed
     * @throws Exception
     */
    public function conduct($data)
    {
        $output = &$data;
        $subject = &$data;
        if (!empty($this->propertyName)) {
            if (strpos($this->propertyName, '.') !== false && is_array($subject)) {
                foreach (explode('.', $this->propertyName) as $segment) {
                    $subject = &$subject[$segment];
                }
            } elseif (is_array($subject)) {
                $subject =& $subject[$this->propertyName];
            }
        }
        $targetType = $this->getTargetType();
        $typeConverter = $this->getTypeConverter();
        if (!$typeConverter->canConvertFrom($subject, $targetType)) {
            throw new Exception(
                sprintf(
                    'TypeConverter %s cannot convert %s to %s',
                    get_class($typeConverter),
                    gettype($subject),
                    $targetType
                ),
                1386292424
            );
        }
        $subject = $this->getTypeConverter()->convertFrom($subject, $targetType);
        if ($output instanceof Error) {
            throw new Exception(
                sprintf(
                    'Conversion of %s to %s was unsuccessful, Error was: %s',
                    gettype($data),
                    $targetType,
                    $output->getMessage()
                ),
                $output->getCode()
            );
        }
        return $output;
    }
}
