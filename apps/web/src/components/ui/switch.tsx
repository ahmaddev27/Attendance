"use client"

import * as React from "react"
import * as SwitchPrimitives from "@radix-ui/react-switch"

import { cn } from "@/lib/utils"

const Switch = React.forwardRef<
  React.ElementRef<typeof SwitchPrimitives.Root>,
  React.ComponentPropsWithoutRef<typeof SwitchPrimitives.Root>
>(({ className, ...props }, ref) => (
  <SwitchPrimitives.Root
    className={cn(
      // Sized up from h-5/w-9 → h-6/w-11 so the thumb has room to breathe
      // and the pill reads as a real control instead of a black lozenge.
      // `bg-primary` (near-black slate from shadcn's palette) was the
      // eyesore users flagged; brand green now signals "on" and matches
      // the neighboring "نشط" label color.
      "peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-success data-[state=unchecked]:bg-hairline-strong",
      className
    )}
    {...props}
    ref={ref}
  >
    <SwitchPrimitives.Thumb
      className={cn(
        // The thumb slides toward the LTR/RTL "end" edge on check. Physical
        // `translate-x-5` always moves the thumb rightward, which in RTL
        // slides it toward the visual "off" side (the right edge is now
        // the start). Guard with `rtl:-translate-x-5` so it slides leftward
        // under `dir="rtl"` and rightward under `dir="ltr"`.
        "pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5 rtl:data-[state=checked]:-translate-x-5 data-[state=unchecked]:translate-x-0"
      )}
    />
  </SwitchPrimitives.Root>
))
Switch.displayName = SwitchPrimitives.Root.displayName

export { Switch }
