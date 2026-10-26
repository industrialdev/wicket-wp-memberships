import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import he from "he";
import { API_URL, WC_API_V3_URL } from "../constants";

/**
 * Resolves a single post or product into a { value, title } option on mount.
 * Makes a single-item API call so the trigger button shows the real name
 * immediately, without waiting for the full list to load.
 *
 * @param {string|number} id        - The saved post/product ID. Skips fetch when falsy.
 * @param {"post"|"product"} type   - Determines which endpoint to hit.
 * @param {string} restSlug         - WP REST slug (e.g. "pages", "posts"). Used when type is "post".
 * @returns {{ option: object|null, isLoading: boolean }}
 */
const useResolvedOption = (id, type, restSlug = "pages") => {
  const [option, setOption] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (!id || id === "-1" || id === "") {
      setOption(null);
      return;
    }

    let cancelled = false;
    setIsLoading(true);

    const fetch = async () => {
      try {
        let title;
        let resolvedId;

        if (type === "product") {
          const product = await apiFetch({
            path: `${WC_API_V3_URL}/products/${id}`,
          });
          title = product.name;
          resolvedId = product.id;
        } else {
          const post = await apiFetch({
            path: addQueryArgs(`${API_URL}/${restSlug}/${id}`, {
              _fields: "id,title",
            }),
          });
          title = he.decode(post.title.rendered);
          resolvedId = post.id;
        }

        if (!cancelled) {
          setOption({ value: resolvedId, title });
        }
      } catch {
        // Leave option as null — ModalPostSelector will show placeholder
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    };

    fetch();

    return () => {
      cancelled = true;
    };
  }, [id, type, restSlug]);

  return { option, isLoading };
};

export default useResolvedOption;
