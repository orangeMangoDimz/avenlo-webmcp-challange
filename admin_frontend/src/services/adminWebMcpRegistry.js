import { registerAdminClientWebMcpTools } from "@/services/adminClientWebMcp";
import { registerAdminIbWebMcpTools } from "@/services/adminIbWebMcp";
import { registerAdminKycWebMcpTools } from "@/services/adminKycWebMcp";
import { registerAdminSalesWebMcpTools } from "@/services/adminSalesWebMcp";
import { registerAdminReportWebMcpTools } from "@/services/adminReportWebMcp";
import { registerAdminAdminLogWebMcpTools } from "@/services/adminAdminLogWebMcp";

export const registerAdminWebMcpTools = ({ authStore, router } = {}) => {
  const cleanups = [
    registerAdminClientWebMcpTools({ authStore, router }),
    registerAdminKycWebMcpTools({ authStore }),
    registerAdminIbWebMcpTools({ authStore, router }),
    registerAdminSalesWebMcpTools({ authStore, router }),
    registerAdminReportWebMcpTools({ authStore, router }),
    registerAdminAdminLogWebMcpTools({ authStore, router }),
  ];

  return () => {
    cleanups.forEach((cleanup) => cleanup());
  };
};
