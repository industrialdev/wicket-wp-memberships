import styled, { keyframes } from "styled-components";
import { BorderedBox, FormFlex } from "../styled_elements";

const shimmer = keyframes`
  0% {
    background-position: 100% 50%;
  }

  100% {
    background-position: 0 50%;
  }
`;

const SkeletonStack = styled.div`
  display: grid;
  gap: 12px;
`;

const SkeletonLabel = styled.div`
  height: 11px;
  width: 160px;
  border-radius: 999px;
  background: linear-gradient(90deg, #d4d8dd 0%, #e7eaee 50%, #d4d8dd 100%);
  background-size: 200% 100%;
  animation: ${shimmer} 1.4s ease-in-out infinite;
`;

const SkeletonLine = styled.div`
  height: ${(props) => props.$height || "32px"};
  width: ${(props) => props.$width || "100%"};
  border-radius: ${(props) => (props.$height === "11px" ? "999px" : "4px")};
  background: linear-gradient(90deg, #d4d8dd 0%, #e7eaee 50%, #d4d8dd 100%);
  background-size: 200% 100%;
  animation: ${shimmer} 1.4s ease-in-out infinite;
`;

const SkeletonRow = styled.div`
  display: grid;
  gap: 12px;
  grid-template-columns: ${(props) => props.$columns || "1fr"};

  @media screen and (max-width: 782px) {
    grid-template-columns: 1fr;
  }
`;

const skeletonPresets = {
  singleField: {
    rows: [{ columns: ["100%"] }],
  },
  fieldWithAction: {
    rows: [{ columns: ["1fr", "180px"] }],
  },
  multiField: {
    rows: [{ columns: ["1fr", "1fr", "180px"] }],
  },
  cycle: {
    rows: [
      { columns: ["1fr", "160px"] },
      { columns: ["100%"] },
      { columns: ["100%"] },
      { columns: ["100%"] },
    ],
  },
};

const AdminLoadingSkeleton = ({
  label,
  boxed = true,
  variant = "singleField",
}) => {
  const preset = skeletonPresets[variant] || skeletonPresets.singleField;
  const content = (
    <SkeletonStack aria-hidden="true">
      <SkeletonLabel aria-label={label} />
      {preset.rows.map((row, rowIndex) => (
        <SkeletonRow
          $columns={row.columns.map((column) => column || "1fr").join(" ")}
          key={`${label}-${rowIndex}`}
        >
          {row.columns.map((column, columnIndex) => (
            <SkeletonLine
              $width={column}
              key={`${label}-${rowIndex}-${columnIndex}`}
            />
          ))}
        </SkeletonRow>
      ))}
    </SkeletonStack>
  );

  if (boxed) {
    return <BorderedBox>{content}</BorderedBox>;
  }

  return <FormFlex>{content}</FormFlex>;
};

export default AdminLoadingSkeleton;
