import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import {
  Flex,
  FlexItem,
  SelectControl,
  TextControl,
  TextareaControl,
} from "@wordpress/components";
import WicketButton from "./WicketButton";
import { ActionRow } from "../styled_elements";
import WicketModal from "./WicketModal";

const createDefaultLocales = (languageCodes = []) =>
  languageCodes.reduce((locales, code) => {
    locales[code] = {
      callout_header: "",
      callout_content: "",
      callout_button_label: "",
    };

    return locales;
  }, {});

const mergeCalloutData = (languageCodes = [], value = {}) => {
  const defaultLocales = createDefaultLocales(languageCodes);

  return {
    ...value,
    locales: Object.keys(defaultLocales).reduce((locales, code) => {
      locales[code] = {
        ...defaultLocales[code],
        ...(value?.locales?.[code] || {}),
      };

      return locales;
    }, {}),
  };
};

const LocalizedCalloutModal = ({
  isOpen,
  title,
  languageCodes,
  value,
  onClose,
  onSave,
}) => {
  const [currentLocale, setCurrentLocale] = useState(languageCodes[0]);
  const [tempValue, setTempValue] = useState(value);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    setCurrentLocale(languageCodes[0]);
    setTempValue(mergeCalloutData(languageCodes, value || {}));
  }, [isOpen, languageCodes, value]);

  const updateLocaleField = (field, fieldValue) => {
    setTempValue((currentValue) => ({
      ...currentValue,
      locales: {
        ...currentValue.locales,
        [currentLocale]: {
          ...currentValue.locales[currentLocale],
          [field]: fieldValue,
        },
      },
    }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSave(tempValue);
    onClose();
  };

  return (
    <WicketModal isOpen={isOpen} onRequestClose={onClose} title={title}>
      <form onSubmit={handleSubmit}>
        <SelectControl
          label={__("Language", "wicket-memberships")}
          onChange={setCurrentLocale}
          options={languageCodes.map((code) => ({ label: code, value: code }))}
          value={currentLocale}
        />

        <TextControl
          label={__("Callout Header", "wicket-memberships")}
          onChange={(fieldValue) =>
            updateLocaleField("callout_header", fieldValue)
          }
          value={tempValue?.locales?.[currentLocale]?.callout_header || ""}
        />

        <TextareaControl
          label={__("Callout Content", "wicket-memberships")}
          onChange={(fieldValue) =>
            updateLocaleField("callout_content", fieldValue)
          }
          value={tempValue?.locales?.[currentLocale]?.callout_content || ""}
        />

        <TextControl
          label={__("Button Label", "wicket-memberships")}
          onChange={(fieldValue) =>
            updateLocaleField("callout_button_label", fieldValue)
          }
          value={tempValue?.locales?.[currentLocale]?.callout_button_label || ""}
        />

        <ActionRow>
          <Flex justify="end">
            <FlexItem>
              <WicketButton type="submit" variant="primary">
                {__("Save", "wicket-memberships")}
              </WicketButton>
            </FlexItem>
          </Flex>
        </ActionRow>
      </form>
    </WicketModal>
  );
};

export default LocalizedCalloutModal;
