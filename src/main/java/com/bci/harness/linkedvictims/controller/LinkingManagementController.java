package com.bci.harness.linkedvictims.controller;

import com.bci.harness.message.entity.Message;
import com.bci.harness.victim.entity.Victim;
import com.bci.harness.victim.service.VictimService;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;

import java.util.ArrayList;
import java.util.List;

@Controller
@RequestMapping("linked-victims")
public class LinkingManagementController {
    private VictimService victimService;

    public LinkingManagementController(VictimService victimService) {
        this.victimService = victimService;
    }

    @GetMapping("/")
    public String listAction(Model model) {
        List<Victim> linkedVictims = new ArrayList<>();
        Victim currentUser = victimService.getCurrentUser();
        List<Message> messages = currentUser.getMessages();
        if (messages != null && !messages.isEmpty()) {
            for (Message message : messages) {
                if (message.getVictims().size() > 1) {
                    for (Victim victim : message.getVictims()) {
                        // add this victim on the list of linked victims
                        if (!victim.equals(currentUser) && !linkedVictims.contains(victim)) {
                            linkedVictims.add(victim);
                        }
                    }
                }
            }
        }

        model.addAttribute("victims", linkedVictims);
        return "linked-victims/list";
    }

    @GetMapping("/{username}")
    public String detailAction(@PathVariable("username") String victimUsername, Model model) {
        Victim victim = victimService.findByUsername(victimUsername);
        model.addAttribute("victim", victim);
        return "linked-victims/detail";
    }
}
