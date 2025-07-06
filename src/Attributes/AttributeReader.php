<?php

namespace HCart\LaravelMultiCart\Attributes;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

class AttributeReader
{
    /**
     * Get tax configuration from model using attributes
     */
    public static function getTaxConfiguration(Model $model): ?TaxConfiguration
    {
        $reflectionClass = new ReflectionClass($model);

        // Check class-level attributes
        $classAttributes = $reflectionClass->getAttributes(TaxConfiguration::class);
        if (! empty($classAttributes)) {
            return $classAttributes[0]->newInstance();
        }

        // Check method-level attributes
        foreach ($reflectionClass->getMethods() as $method) {
            $methodAttributes = $method->getAttributes(TaxConfiguration::class);
            if (! empty($methodAttributes)) {
                return $methodAttributes[0]->newInstance();
            }
        }

        // Check property-level attributes
        $properties = ['tax_settings', 'tax_configuration', 'price'];
        foreach ($properties as $propertyName) {
            if ($reflectionClass->hasProperty($propertyName)) {
                $property = $reflectionClass->getProperty($propertyName);
                $propertyAttributes = $property->getAttributes(TaxConfiguration::class);
                if (! empty($propertyAttributes)) {
                    return $propertyAttributes[0]->newInstance();
                }
            }
        }

        return null;
    }

    /**
     * Get shipping configuration from model using attributes
     */
    public static function getShippingConfiguration(Model $model): ?ShippingConfiguration
    {
        $reflectionClass = new ReflectionClass($model);

        // Check class-level attributes
        $classAttributes = $reflectionClass->getAttributes(ShippingConfiguration::class);
        if (! empty($classAttributes)) {
            return $classAttributes[0]->newInstance();
        }

        // Check method-level attributes
        foreach ($reflectionClass->getMethods() as $method) {
            $methodAttributes = $method->getAttributes(ShippingConfiguration::class);
            if (! empty($methodAttributes)) {
                return $methodAttributes[0]->newInstance();
            }
        }

        // Check property-level attributes
        $properties = ['shipping_settings', 'shipping_configuration', 'weight'];
        foreach ($properties as $propertyName) {
            if ($reflectionClass->hasProperty($propertyName)) {
                $property = $reflectionClass->getProperty($propertyName);
                $propertyAttributes = $property->getAttributes(ShippingConfiguration::class);
                if (! empty($propertyAttributes)) {
                    return $propertyAttributes[0]->newInstance();
                }
            }
        }

        return null;
    }

    /**
     * Get all tax configurations from model (class + method + property)
     */
    public static function getAllTaxConfigurations(Model $model): array
    {
        $configurations = [];
        $reflectionClass = new ReflectionClass($model);

        // Class-level
        $classAttributes = $reflectionClass->getAttributes(TaxConfiguration::class);
        foreach ($classAttributes as $attribute) {
            $configurations['class'] = $attribute->newInstance();
        }

        // Method-level
        foreach ($reflectionClass->getMethods() as $method) {
            $methodAttributes = $method->getAttributes(TaxConfiguration::class);
            foreach ($methodAttributes as $attribute) {
                $configurations['method_'.$method->getName()] = $attribute->newInstance();
            }
        }

        // Property-level
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyAttributes = $property->getAttributes(TaxConfiguration::class);
            foreach ($propertyAttributes as $attribute) {
                $configurations['property_'.$property->getName()] = $attribute->newInstance();
            }
        }

        return $configurations;
    }

    /**
     * Get all shipping configurations from model (class + method + property)
     */
    public static function getAllShippingConfigurations(Model $model): array
    {
        $configurations = [];
        $reflectionClass = new ReflectionClass($model);

        // Class-level
        $classAttributes = $reflectionClass->getAttributes(ShippingConfiguration::class);
        foreach ($classAttributes as $attribute) {
            $configurations['class'] = $attribute->newInstance();
        }

        // Method-level
        foreach ($reflectionClass->getMethods() as $method) {
            $methodAttributes = $method->getAttributes(ShippingConfiguration::class);
            foreach ($methodAttributes as $attribute) {
                $configurations['method_'.$method->getName()] = $attribute->newInstance();
            }
        }

        // Property-level
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyAttributes = $property->getAttributes(ShippingConfiguration::class);
            foreach ($propertyAttributes as $attribute) {
                $configurations['property_'.$property->getName()] = $attribute->newInstance();
            }
        }

        return $configurations;
    }
}
