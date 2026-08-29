<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ComboboxItemIndicator, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
  <ComboboxItemIndicator
    data-slot="combobox-item-indicator"
    v-bind="forwarded"
    :class="cn('pointer-events-none flex size-3.5 items-center justify-center text-primary', props.class)"
  >
    <slot />
  </ComboboxItemIndicator>
</template>
