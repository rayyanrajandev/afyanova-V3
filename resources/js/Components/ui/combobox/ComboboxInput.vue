<script setup>
import { Search } from "lucide-vue-next";
import { reactiveOmit } from "@vueuse/core";
import { ComboboxInput, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  modelValue: { type: String, required: false },
  displayValue: { type: Function, required: false },
  placeholder: { type: String, default: "Search..." },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const emits = defineEmits(["update:modelValue"]);

const delegatedProps = reactiveOmit(props, "class");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <div class="flex items-center border-b border-border/60 bg-muted/20 px-2.5 py-1.5 gap-2">
    <Search class="w-3.5 h-3.5 shrink-0 text-muted-foreground" />
    <ComboboxInput
      data-slot="combobox-input"
      :placeholder="placeholder"
      :class="cn(
        'flex-1 bg-transparent text-xs text-foreground placeholder:text-muted-foreground outline-none disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )"
      v-bind="{ ...$attrs, ...forwarded }"
    />
  </div>
</template>
