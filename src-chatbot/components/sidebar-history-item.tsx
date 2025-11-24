import {
  SidebarMenuAction,
  SidebarMenuButton,
  SidebarMenuItem,
} from './ui/sidebar';
import Link from 'next/link';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from './ui/dropdown-menu';
import {
  MoreHorizontalIcon,
  ShareIcon,
} from './icons';
import { memo } from 'react';
import { getConfig } from '@/lib/constants';

// Define local Chat type as the original schema is removed
interface Chat {
  id: string;
  title: string;
  visibility: 'public' | 'private'; // Assuming these are the only possible values
  // Add other fields if they were essential from the original Chat type and are used elsewhere
  // userId?: string;
  // createdAt?: Date;
}

const PureChatItem = ({
  chat,
  isActive,
  setOpenMobile,
}: {
  chat: Chat;
  isActive: boolean;
  setOpenMobile: (open: boolean) => void;
}) => {
  const config = getConfig();
  const editUrl = `${config.wp_admin_url}post.php?post=${chat.id}&action=edit`;

  return (
    <SidebarMenuItem>
      <SidebarMenuButton asChild isActive={isActive}>
        <Link href={`?page=personalos-chatbot&id=${chat.id}`} onClick={() => setOpenMobile(false)}>
          <span>{chat.title}</span>
        </Link>
      </SidebarMenuButton>

      <DropdownMenu modal={true}>
        <DropdownMenuTrigger asChild>
          <SidebarMenuAction
            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground mr-0.5"
            showOnHover={!isActive}
          >
            <MoreHorizontalIcon />
            <span className="sr-only">More</span>
          </SidebarMenuAction>
        </DropdownMenuTrigger>

        <DropdownMenuContent side="bottom" align="end">
          <DropdownMenuItem asChild>
            <a
              href={editUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex gap-2 items-center cursor-pointer"
            >
              <ShareIcon />
              <span>Note Transcript</span>
            </a>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  );
};

export const ChatItem = memo(PureChatItem, (prevProps, nextProps) => {
  if (prevProps.isActive !== nextProps.isActive) return false;
  return true;
});
