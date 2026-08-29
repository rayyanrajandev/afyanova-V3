<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ComboboxEmpty } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <ComboboxEmpty
    data-slot="combobox-empty"
    v-bind="delegatedProps"
    :class="cn('py-4 px-3 text-center text-xs text-muted-foreground font-medium', props.class)"
  >
    <slot>No results found.</slot>
  </ComboboxEmpty>
</template>
