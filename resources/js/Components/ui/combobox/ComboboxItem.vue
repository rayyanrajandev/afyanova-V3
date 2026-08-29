<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ComboboxItem, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  value: { type: null, required: false },
  disabled: { type: Boolean, required: false },
  textValue: { type: String, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
const emits = defineEmits(["select"]);

const delegatedProps = reactiveOmit(props, "class");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxItem
    data-slot="combobox-item"
    v-bind="forwarded"
    :class="cn(
      'relative flex w-full cursor-pointer items-center justify-between gap-2 rounded-md py-1.5 px-2.5 text-xs font-medium outline-none select-none transition-colors',
      'data-highlighted:bg-primary/10 data-highlighted:text-primary text-foreground',
      'data-disabled:pointer-events-none data-disabled:opacity-50',
      props.class
    )"
  >
    <slot />
  </ComboboxItem>
</template>
