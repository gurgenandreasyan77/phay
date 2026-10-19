"use client";

import { ChatThread } from "../_components/chat/ChatThread";
import { notifySupportActivity } from "../_components/chat/unread";

export function BusinessSupportChat() {
  return (
    <ChatThread
      url="/api/admin/support/messages"
      side="business"
      emptyText="Հաղորդագրություններ դեռ չկան։ Գրեք մեզ առաջինը։"
      onActivity={notifySupportActivity}
      className="max-w-3xl"
    />
  );
}
