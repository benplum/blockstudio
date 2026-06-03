import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { selectors } from '@/blocks/store/selectors';

type MediaValue = string[] | string | number[] | number | null | undefined;

type MediaItem = {
  id: number | string;
  [key: string]: unknown;
};

const pendingMediaIds = new Set<string>();

const normalizeMediaIds = (value: MediaValue) => {
  const values = Array.isArray(value) ? value : [value];

  return Array.from(
    new Set(
      values
        .map((item) => `${item ?? ''}`.trim())
        .filter((item) => /^[1-9]\d*$/.test(item)),
    ),
  );
};

export const useMedia = (v: MediaValue) => {
  const { setMedia } = useDispatch('blockstudio/blocks');
  const media = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getMedia(),
    [],
  );
  const ids = normalizeMediaIds(v);
  const idsKey = ids.join(',');

  useEffect(() => {
    const requestedIds = idsKey ? idsKey.split(',') : [];

    if (!requestedIds.length) return;

    const existingIds = new Set(Object.keys(media || {}).map((item) => item));
    const newIds = requestedIds.filter(
      (item) => !existingIds.has(item) && !pendingMediaIds.has(item),
    );

    if (!newIds.length) return;

    newIds.forEach((item) => pendingMediaIds.add(item));

    apiFetch({
      path: `/wp/v2/media?include=${newIds
        .map((item) => encodeURIComponent(item))
        .join(',')}&per_page=${newIds.length}`,
    })
      .then((response) => {
        const nextMedia = (response as MediaItem[]).reduce<
          Record<string, MediaItem>
        >((result, item) => {
          result[`${item.id}`] = item;
          return result;
        }, {});

        if (Object.keys(nextMedia).length) {
          setMedia(nextMedia);
        }
      })
      .catch(() => {})
      .finally(() => {
        newIds.forEach((item) => pendingMediaIds.delete(item));
      });
  }, [idsKey, media, setMedia]);
};
