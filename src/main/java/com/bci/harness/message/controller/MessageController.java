package com.bci.harness.message.controller;

import com.bci.harness.message.entity.Message;
import com.bci.harness.message.service.MessageService;
import com.bci.harness.victim.entity.Victim;
import com.bci.harness.victim.service.VictimService;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;

@Controller
@RequestMapping("/messages")
public class MessageController {
    private VictimService victimService;

    private MessageService messageService;

    private static final Logger LOGGER = LoggerFactory.getLogger(MessageController.class);

    public MessageController(MessageService messageService, VictimService victimService) {
        this.messageService = messageService;
        this.victimService = victimService;
    }

    @GetMapping("/list")
    public String listAction(Model model) {
        Victim victim = victimService.getCurrentUser();
        model.addAttribute("victim", victim);
        return "messages/list";
    }

    @GetMapping("/detail")
    public String detailAction(Model model) {
        model.addAttribute("message", new Message());
        return "messages/detail";
    }

    @PostMapping("/detail")
    public String postDetailAction(@ModelAttribute("message") Message message, BindingResult result) {
        if (result.hasErrors()) {
            return "messages/detail";  // Ensure this matches your FreeMarker template file name
        }

        Victim victim = victimService.getCurrentUser();

        Message messageEntity = messageService.findByContent(message.getContent());
        if (messageEntity == null) {
            // Create a new insult if it does not exist
            messageService.save(message);
            victim.getMessages().add(message);
        } else {
            victim.getMessages().add(messageEntity);
        }

        victimService.save(victim);
        return "redirect:/messages/list";
    }

    @GetMapping("/ping")
    public ResponseEntity<String> ping() {
        return ResponseEntity.ok("Poong!");
    }
}
