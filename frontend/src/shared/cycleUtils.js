export const getDefaultCycleData = () => ({
  cycle_type: "calendar",
  anniversary_data: {
    period_count: "1",
    period_type: "year",
    align_end_dates_enabled: false,
    align_end_dates_type: "first-day-of-month",
  },
  calendar_items: [],
});

export const normalizeCycleData = (cycleData) => {
  const defaultCycleData = getDefaultCycleData();

  return {
    ...defaultCycleData,
    ...(cycleData || {}),
    anniversary_data: {
      ...defaultCycleData.anniversary_data,
      ...(cycleData?.anniversary_data || {}),
    },
    calendar_items: Array.isArray(cycleData?.calendar_items)
      ? cycleData.calendar_items
      : [],
  };
};
