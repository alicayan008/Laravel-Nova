<?php

namespace Whitecube\NovaFlexibleContent\Http;

use Illuminate\Http\Request;
use Whitecube\NovaFlexibleContent\Http\FlexibleAttribute;

trait ParsesFlexibleAttributes
{
    /**
     * The registered flexible field attributes
     *
     * @var string
     */
    protected $registered = [];

    /**
     * Check if given request should be handled by the middleware
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function requestHasParsableFlexibleInputs(Request $request)
    {
        return (in_array($request->method(), ['POST','PUT']) &&
                is_array($request->input(FlexibleAttribute::REGISTER)));
    }

    /**
     * Transform the request's flexible values
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getParsedFlexibleInputs(Request $request)
    {
        $this->registerFlexibleFields($request->input(FlexibleAttribute::REGISTER, []));

        return array_reduce(array_keys($request->all()), function($carry, $attribute) use ($request) {
            $value = $request->input($attribute);

            if(!$this->isFlexibleAttribute($attribute, $value)) return $carry;

            $carry[$attribute] = $this->getParsedFlexibleValue($value);

            return $carry;
        }, []);
    }

    /**
     * Apply JSON decode and recursively check for nested values
     *
     * @param  mixed $value
     * @return array
     */
    protected function getParsedFlexibleValue($value)
    {
        if(is_string($value)) {
            $raw = json_decode($value, JSON_OBJECT_AS_ARRAY);
        } else {
            $raw = $value;
        }

        if(!is_array($raw)) return $value;

        return array_map(function($group) {
            return $this->getParsedFlexibleGroup($group);
        }, $raw);
    }

    /**
     * Cleans & prepares a filled group
     *
     * @param  array $group
     * @return array
     */
    protected function getParsedFlexibleGroup($group)
    {
        $clean = [
            'layout' => $group['layout'] ?? null,
            'key' => $group['key'] ?? null,
            'attributes' => [],
        ];

        foreach ($group['attributes'] ?? [] as $attribute => $value) {
            $this->fillFlexibleAttributes($clean['attributes'], $clean['key'], $attribute, $value);
        }

        foreach ($clean['attributes'] as $attribute => $value) {
            if(!$this->isFlexibleAttribute($attribute, $value)) continue;
            $clean['attributes'][$attribute] = $this->getParsedFlexibleValue($value);
        }

        return $clean;
    }

    /**
     * Fill a flexible group's attributes with cleaned attributes & values
     *
     * @param  array $attributes
     * @param  string $group
     * @param  string $attribute
     * @param  string $value
     * @return array
     */
    protected function fillFlexibleAttributes(&$attributes, $group, $attribute, $value)
    {
        $attribute = $this->parseAttribute($attribute, $group);

        if($attribute->isFlexibleFieldsRegister()) {
            $this->registerFlexibleFields($value, $group);
            return;
        }

        if(!$attribute->isAggregate()) {
            $attributes[$attribute->name] = $value;
            return;
        }

        if(!isset($attributes[$attribute->name])) {
            $attributes[$attribute->name] = [];
        } else if (!is_array($attributes[$attribute->name])) {
            $attributes[$attribute->name] = [$attributes[$attribute->name]];
        }

        if($attribute->key === true) {
            $attributes[$attribute->name][] = $value;
        } else {
            $attributes[$attribute->name][$attribute->key] = $value;
        }
    }

    /**
     * Analyse and clean up the raw attribute
     *
     * @param  string  $attribute
     * @param  string  $group
     * @return \Whitecube\NovaFlexibleContent\Http\FlexibleAttribute
     */
    protected function parseAttribute($attribute, $group)
    {
        return new FlexibleAttribute($attribute, $group);
    }

    /**
     * Add flexible attributes to the register
     *
     * @param  mixed $value
     * @param  null|string $group
     * @return void
     */
    protected function registerFlexibleFields($value, $group = null)
    {
        if(!is_array($value)) {
            return $this->registerFlexibleField($value, $group);
        }

        foreach ($value as $attribute) {
            $this->registerFlexibleField($attribute, $group);
        }
    }

    /**
     * Add an attribute to the register
     *
     * @param  mixed $attribute
     * @param  null|string $group
     * @return void
     */
    protected function registerFlexibleField($attribute, $group = null)
    {
        $attribute = $this->parseAttribute(strval($attribute), $group);

        $this->registered[] = $attribute;
    }

    /**
     * Check if given attribute is a registered and usable 
     * flexible attribute
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function isFlexibleAttribute($attribute, $value)
    {
        if(!$this->getFlexibleAttribute($attribute)) {
            return false;
        }
        
        if(!$value || !is_string($value)) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve a registered flexible attribute
     *
     * @param  string $attribute
     * @return \Whitecube\NovaFlexibleContent\Http\FlexibleAttribute
     */
    protected function getFlexibleAttribute($attribute)
    {
        foreach ($this->registered as $registered) {
            if($registered->name === $attribute) return $registered;
        }
    }
}
