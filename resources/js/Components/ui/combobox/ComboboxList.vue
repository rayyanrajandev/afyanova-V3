<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ComboboxContent, ComboboxPortal, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  position: { type: String, required: false, default: "popper" },
  align: { type: String, required: false, default: "start" },
  sideOffset: { type: Number, required: false, default: 4 },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
const emits = defineEmits([
  "escapeKeyDown",
  "pointerDownOutside",
  "focusOutside",
  "interactOutside",
]);

const delegatedProps = reactiveOmit(props, "class");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxPortal>
    <ComboboxContent
      data-slot="combobox-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="cn(
        'z-50 min-w-[180px] w-[var(--reka-combobox-trigger-width)] max-h-72 overflow-hidden rounded-lg border border-border/80 bg-popover text-popover-foreground shadow-xl',
        'data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 data-[side=bottom]:slide-in-from-top-1 data-[side=top]:slide-in-from-bottom-1',
        props.class
      )"
    >
      <slot />
    </ComboboxContent>
  </ComboboxPortal>
</template>
